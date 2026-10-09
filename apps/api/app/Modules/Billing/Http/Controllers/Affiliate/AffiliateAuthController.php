<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Affiliate;

use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Support\Affiliates;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;
use Symfony\Component\HttpFoundation\Response;

/** Partners sign up and sign in with their own account (not a shop's). */
final class AffiliateAuthController
{
    public function register(Request $request, Affiliates $affiliates): JsonResponse
    {
        $request->merge(['phone' => self::e164($request->input('phone'))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'phone:EG', Rule::unique('affiliates', 'phone')],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('affiliates', 'email')],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
            'code' => ['nullable', 'string', 'min:4', 'max:20', 'regex:/^[A-Za-z0-9]+$/'],
            'channel' => ['nullable', 'string', 'max:120'],
            'terms' => ['accepted'],
        ], attributes: ['name' => 'الاسم', 'phone' => 'الموبايل', 'password' => 'كلمة السر', 'code' => 'الكود', 'channel' => 'بتسوّق فين', 'terms' => 'شروط البرنامج'], messages: [
            'phone.unique' => 'الموبايل ده عنده حساب شريك. ادخل بيه.',
        ]);

        $affiliate = Affiliate::query()->create([
            'name' => trim($data['name']),
            'phone' => $data['phone'],
            'email' => isset($data['email']) ? mb_strtolower($data['email']) : null,
            'password' => $data['password'],
            'code' => $affiliates->newCode($data['code'] ?? null),
            'channel' => $data['channel'] ?? null,
            'last_login_at' => now(),
        ]);

        return response()->json(['token' => $this->token($affiliate), 'code' => $affiliate->code], Response::HTTP_CREATED);
    }

    public function login(Request $request): JsonResponse
    {
        $request->merge(['phone' => self::e164($request->input('phone'))]);
        $data = $request->validate(['phone' => ['required', 'string'], 'password' => ['required', 'string']]);
        $key = 'affiliate-login:'.sha1($data['phone'].'|'.$request->ip());
        if (RateLimiter::tooManyAttempts($key, 5)) {
            throw new DomainRuleException('محاولات كتير غلط. جرّب بعد '.ceil(RateLimiter::availableIn($key) / 60).' دقيقة.', 'login_locked', Response::HTTP_TOO_MANY_REQUESTS);
        }
        $affiliate = Affiliate::query()->where('phone', $data['phone'])->first();
        if ($affiliate === null || ! Hash::check($data['password'], $affiliate->getAuthPassword())) {
            RateLimiter::hit($key, 15 * 60);
            throw new DomainRuleException('الموبايل أو كلمة السر غلط.', 'invalid_credentials', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        RateLimiter::clear($key);
        $affiliate->update(['last_login_at' => now()]);

        return response()->json(['token' => $this->token($affiliate)]);
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->json(['ok' => true]);
    }

    private function token(Affiliate $affiliate): string
    {
        return $affiliate->createToken('affiliate', ['affiliate'], now()->addDays((int) config('billing.affiliates.token_days')))->plainTextToken;
    }

    private static function e164(mixed $phone): mixed
    {
        if (! is_string($phone) || trim($phone) === '') {
            return $phone;
        }
        try {
            return (new PhoneNumber(trim($phone), 'EG'))->formatE164();
        } catch (\Throwable) {
            return $phone;
        }
    }
}
