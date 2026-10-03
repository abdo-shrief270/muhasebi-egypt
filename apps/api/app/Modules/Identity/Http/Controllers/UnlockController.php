<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Auth\AppUnlock;
use App\Modules\Identity\Models\Passkey;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Security\WebAuthn;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * «قفل التطبيق»: this device proves it's still its user — a passkey (fingerprint / face) or the
 * approval PIN. Too many wrong tries sign the device out.
 */
final class UnlockController
{
    public function __construct(
        private readonly WebAuthn $webauthn,
        private readonly AppUnlock $unlock,
        private readonly Cache $cache,
    ) {}

    /** A challenge for navigator.credentials.get() with this user's passkeys. */
    public function options(Request $request): JsonResponse
    {
        $challenge = WebAuthn::challenge();
        $this->cache->put('passkey-unlock:'.$this->unlock->tokenId(), $challenge, now()->addMinutes(5));

        return response()->json(['data' => [
            'challenge' => $challenge,
            'rpId' => $this->webauthn->rpId(),
            'allowCredentials' => Passkey::query()->where('user_id', $request->user()->id)->pluck('credential_id')
                ->map(fn (string $id) => ['type' => 'public-key', 'id' => $id])->all(),
            'userVerification' => 'required',
            'timeout' => 60000,
        ]]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'pin' => ['required_without:passkey', 'nullable', 'string', 'regex:/^\d{4,6}$/'],
            'passkey' => ['required_without:pin', 'nullable', 'array'],
            'passkey.id' => ['required_with:passkey', 'string', 'max:512'],
            'passkey.client_data_json' => ['required_with:passkey', 'string', 'max:4000'],
            'passkey.authenticator_data' => ['required_with:passkey', 'string', 'max:4000'],
            'passkey.signature' => ['required_with:passkey', 'string', 'max:4000'],
        ]);
        $user = $request->user();

        if (isset($data['passkey'])) {
            $p = $data['passkey'];
            $key = Passkey::query()->where('user_id', $user->id)->where('credential_id', $p['id'])->first();
            $challenge = (string) $this->cache->pull('passkey-unlock:'.$this->unlock->tokenId(), '');
            $count = $key === null || $challenge === '' ? null : $this->webauthn->verify(
                $challenge,
                $key->public_key,
                $key->sign_count,
                WebAuthn::unb64($p['client_data_json']),
                WebAuthn::unb64($p['authenticator_data']),
                WebAuthn::unb64($p['signature']),
            );
            if ($count === null) {
                $this->refuse('البصمة مش متعرّفة على الحساب ده.', 'passkey_incorrect');
            }
            $key->forceFill(['sign_count' => $count, 'last_used_at' => now()])->save();
        } else {
            if ($user->pin_hash === null) {
                throw new DomainRuleException('مفيش PIN متسجّل للحساب ده. حطه من «الأمان».', 'pin_not_set');
            }
            if (! Hash::check((string) $data['pin'], (string) $user->pin_hash)) {
                $this->refuse('الـ PIN غلط.', 'pin_incorrect');
            }
        }

        return response()->json(['data' => ['verified_until' => $this->unlock->verified()]]);
    }

    private function refuse(string $message, string $code): never
    {
        if ($this->unlock->failed()) {
            $token = request()->user()?->currentAccessToken();
            if ($token instanceof PersonalAccessToken) {
                $token->delete();
            }
            throw new DomainRuleException('محاولات غلط كتير، فخرّجناك من الجهاز ده. ادخل تاني بكلمة السر.', 'unlock_locked_out', 401);
        }

        throw new DomainRuleException($message, $code);
    }
}
