<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Billing\Models\PlatformAdminAction;
use App\Modules\Billing\Support\AdminLog;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Symfony\Component\HttpFoundation\Response;

/**
 * Sign-in for platform admins (a separate account from any shop): a few wrong tries lock the
 * email from that IP for a while, every attempt is logged, and tokens expire within hours.
 */
final class AdminAuthController
{
    public function __construct(private readonly AdminLog $log) {}

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string'], 'code' => ['required', 'string', 'max:10']], attributes: ['code' => 'كود التطبيق']);
        $email = mb_strtolower($data['email']);
        $key = 'admin-login:'.sha1($email.'|'.$request->ip());

        if (RateLimiter::tooManyAttempts($key, (int) config('billing.admin.max_login_attempts'))) {
            $this->log->record('login_locked', details: ['email' => $email]);
            throw new DomainRuleException('محاولات كتير غلط. جرّب بعد '.ceil(RateLimiter::availableIn($key) / 60).' دقيقة.', 'login_locked', Response::HTTP_TOO_MANY_REQUESTS);
        }

        $admin = PlatformAdmin::query()->where('email', $email)->first();
        if ($admin === null || ! $admin->is_active || ! Hash::check($data['password'], $admin->getAuthPassword())) {
            RateLimiter::hit($key, (int) config('billing.admin.lockout_minutes') * 60);
            $this->log->record('login_failed', details: ['email' => $email]);
            throw new DomainRuleException('الإيميل أو كلمة السر أو الكود غلط.', 'invalid_credentials', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        // The authenticator code is required for every admin (set up from the server: billing:admin-2fa).
        if (! $admin->hasTwoFactor()) {
            $this->log->record('login_failed', details: ['email' => $email, 'reason' => 'no two-factor']);
            throw new DomainRuleException('التحقق بخطوتين مش متفعّل للحساب ده. فعّله من السيرفر: php artisan billing:admin-2fa', 'two_factor_required', Response::HTTP_FORBIDDEN);
        }
        if (! $admin->verifyCode($data['code'])) {
            RateLimiter::hit($key, (int) config('billing.admin.lockout_minutes') * 60);
            $this->log->record('login_failed', details: ['email' => $email, 'reason' => 'code']);
            throw new DomainRuleException('الإيميل أو كلمة السر أو الكود غلط.', 'invalid_credentials', Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        RateLimiter::clear($key);
        $admin->update(['last_login_at' => now()]);
        $this->log->record('login', admin: $admin);
        $token = $admin->createToken('admin', ['admin'], now()->addHours((int) config('billing.admin.token_hours')));

        return response()->json(['token' => $token->plainTextToken, 'expires_at' => $token->accessToken->expires_at?->toIso8601String(), 'admin' => $this->present($admin)]);
    }

    /** The admins' own log, newest first. */
    public function activity(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('platform-admin'), 403);

        return response()->json(['data' => PlatformAdminAction::query()->orderByDesc('seq')->limit(200)->get()->map(fn (PlatformAdminAction $a) => [
            'action' => $a->action,
            'admin_name' => $a->admin_name,
            'tenant_id' => $a->tenant_id,
            'details' => $a->details,
            'ip' => $a->ip,
            'created_at' => $a->created_at->toIso8601String(),
        ])]);
    }

    public function me(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('platform-admin'), 403);

        return response()->json(['data' => $this->present($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('platform-admin'), 403);
        $request->user()->currentAccessToken()?->delete();
        $this->log->record('logout');

        return response()->json(['data' => null]);
    }

    /** @return array<string, mixed> */
    private function present(PlatformAdmin $admin): array
    {
        return ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email];
    }
}
