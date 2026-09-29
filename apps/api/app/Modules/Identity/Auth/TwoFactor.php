<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth;

use App\Modules\Identity\Models\User;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Security\Totp;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Two-factor sign-in with an authenticator app (TOTP). The secret is encrypted at rest; the
 * one-time recovery codes are only stored as SHA-256 hashes and shown once.
 */
final class TwoFactor
{
    public const RECOVERY_CODES = 8;

    public function __construct(private readonly Auditor $audit) {}

    /**
     * Starts (or restarts) the setup: a new secret the user scans. Not active until confirmed.
     *
     * @return array{secret: string, otpauth_url: string}
     */
    public function begin(User $user, string $password): array
    {
        $this->checkPassword($user, $password);

        if ($user->hasTwoFactor()) {
            throw new DomainRuleException('التحقق بخطوتين شغال بالفعل.', 'two_factor_already_enabled');
        }

        $secret = Totp::generateSecret();
        $user->forceFill([
            'two_factor_secret' => $secret,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();

        return [
            'secret' => $secret,
            'otpauth_url' => Totp::provisioningUri($secret, $user->phone, (string) config('app.name')),
        ];
    }

    /**
     * Turns it on once the user proves the app works; returns the recovery codes (shown only now).
     *
     * @return list<string>
     */
    public function confirm(User $user, string $code): array
    {
        return $this->andRefresh($user, fn (): array => DB::transaction(function () use ($user, $code): array {
            $user = $this->locked($user);

            if ($user->hasTwoFactor()) {
                throw new DomainRuleException('التحقق بخطوتين شغال بالفعل.', 'two_factor_already_enabled');
            }
            if ($user->two_factor_secret === null) {
                throw new DomainRuleException('ابدأ التفعيل الأول.', 'two_factor_not_started');
            }

            $step = Totp::verify($user->two_factor_secret, $code);
            if ($step === null) {
                throw new DomainRuleException('الكود غلط أو خلص وقته. اكتب الكود اللي ظاهر دلوقتي في التطبيق.', 'two_factor_invalid_code');
            }

            [$codes, $hashes] = $this->newRecoveryCodes();
            $user->forceFill([
                'two_factor_confirmed_at' => now(),
                'two_factor_recovery_codes' => $hashes,
                'two_factor_last_step' => $step,
            ])->save();

            $this->audit->record('auth.two_factor_enabled', "فعّل التحقق بخطوتين لحساب «{$user->name}»", $user);

            return $codes;
        }));
    }

    public function disable(User $user, string $password, string $code): void
    {
        $this->checkPassword($user, $password);

        $this->andRefresh($user, fn () => DB::transaction(function () use ($user, $code): void {
            $user = $this->locked($user);

            if (! $user->hasTwoFactor()) {
                throw new DomainRuleException('التحقق بخطوتين مش شغال.', 'two_factor_not_enabled');
            }
            if ($this->consume($user, $code) === null) {
                throw new DomainRuleException('الكود غلط.', 'two_factor_invalid_code');
            }

            $this->clear($user);
            $this->audit->record('auth.two_factor_disabled', "قفل التحقق بخطوتين لحساب «{$user->name}»", $user);
        }));
    }

    /** The owner turns it off for an employee who lost their phone and their recovery codes. */
    public function reset(User $user): void
    {
        if ($user->is_owner) {
            throw new DomainRuleException('صاحب المحل يقفل التحقق بخطوتين من صفحة الأمان بكلمة السر والكود.', 'owner_two_factor_self');
        }

        $this->andRefresh($user, fn () => DB::transaction(function () use ($user): void {
            $this->clear($this->locked($user));
            $user->tokens()->delete();
            $this->audit->record('users.two_factor_reset', "لغى التحقق بخطوتين لـ «{$user->name}» وخرّجه من كل الأجهزة", $user);
        }));
    }

    /**
     * @return list<string>
     */
    public function regenerateRecoveryCodes(User $user, string $password): array
    {
        $this->checkPassword($user, $password);

        return $this->andRefresh($user, fn (): array => DB::transaction(function () use ($user): array {
            $user = $this->locked($user);

            if (! $user->hasTwoFactor()) {
                throw new DomainRuleException('التحقق بخطوتين مش شغال.', 'two_factor_not_enabled');
            }

            [$codes, $hashes] = $this->newRecoveryCodes();
            $user->forceFill(['two_factor_recovery_codes' => $hashes])->save();
            $this->audit->record('auth.recovery_codes_regenerated', "عمل أكواد استرجاع جديدة لحساب «{$user->name}»", $user);

            return $codes;
        }));
    }

    /**
     * Checks an authenticator code or a recovery code and uses it up (a TOTP step can't be
     * replayed, a recovery code works once). Returns 'totp' / 'recovery', or null when wrong.
     * Call inside a transaction with the user row locked.
     */
    public function consume(User $user, string $code): ?string
    {
        $code = (string) preg_replace('/\s+/', '', $code);

        if (preg_match('/^\d{'.Totp::DIGITS.'}$/', $code) === 1) {
            $step = Totp::verify((string) $user->two_factor_secret, $code, afterStep: $user->two_factor_last_step);
            if ($step === null) {
                return null;
            }
            $user->forceFill(['two_factor_last_step' => $step])->save();

            return 'totp';
        }

        $hash = self::hashRecoveryCode($code);
        $remaining = $user->two_factor_recovery_codes ?? [];
        $index = array_search($hash, $remaining, true);
        if ($index === false) {
            return null;
        }

        unset($remaining[$index]);
        $user->forceFill(['two_factor_recovery_codes' => array_values($remaining)])->save();
        $this->audit->record('auth.recovery_code_used', 'دخل بكود استرجاع (فاضل '.count($remaining).')', $user);

        return 'recovery';
    }

    public function remainingRecoveryCodes(User $user): int
    {
        return count($user->two_factor_recovery_codes ?? []);
    }

    public function locked(User $user): User
    {
        return User::query()->whereKey($user->getKey())->lockForUpdate()->firstOrFail();
    }

    /**
     * Runs a change made on a locked copy of the user, then reloads the caller's instance.
     *
     * @template T
     *
     * @param  callable(): T  $change
     * @return T
     */
    private function andRefresh(User $user, callable $change): mixed
    {
        try {
            return $change();
        } finally {
            $user->refresh();
        }
    }

    public static function hashRecoveryCode(string $code): string
    {
        return hash('sha256', strtolower((string) preg_replace('/[^A-Za-z0-9]/', '', $code)));
    }

    private function clear(User $user): void
    {
        $user->forceFill([
            'two_factor_secret' => null,
            'two_factor_confirmed_at' => null,
            'two_factor_recovery_codes' => null,
            'two_factor_last_step' => null,
        ])->save();
    }

    /**
     * @return array{0: list<string>, 1: list<string>}
     */
    private function newRecoveryCodes(): array
    {
        $codes = [];
        for ($i = 0; $i < self::RECOVERY_CODES; $i++) {
            $raw = strtolower(Str::random(10));
            $codes[] = substr($raw, 0, 5).'-'.substr($raw, 5);
        }

        return [$codes, array_map(self::hashRecoveryCode(...), $codes)];
    }

    private function checkPassword(User $user, string $password): void
    {
        if (! Hash::check($password, $user->password)) {
            throw new DomainRuleException('كلمة السر غلط.', 'invalid_password');
        }
    }
}
