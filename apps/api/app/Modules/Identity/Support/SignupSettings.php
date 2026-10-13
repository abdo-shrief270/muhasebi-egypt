<?php

declare(strict_types=1);

namespace App\Modules\Identity\Support;

use App\Modules\Identity\Contracts\SignupGate;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Facades\DB;

/**
 * The signup_settings row (cached as an array); until the admins save one, `services.signup`
 * (SIGNUP_OPEN / SIGNUP_WHATSAPP).
 */
final class SignupSettings implements SignupGate
{
    private const CACHE = 'identity:signup:v1';

    public const DEFAULT_MESSAGE = 'التسجيل دلوقتي بيتم مع فريقنا عشان نساعدك تجهّز محلك من أول يوم. كلّمنا واتساب وهنفتحلك الحساب.';

    public function __construct(private readonly Cache $cache) {}

    public function get(): array
    {
        return $this->cache->rememberForever(self::CACHE, function (): array {
            $row = DB::table('signup_settings')->where('id', 1)->first();

            return [
                'open' => $row === null ? (bool) config('services.signup.open') : (bool) $row->open,
                'whatsapp' => $row === null ? (config('services.signup.whatsapp') ?: null) : $row->whatsapp,
                'message' => ($row?->message ?? null) ?: self::DEFAULT_MESSAGE,
            ];
        });
    }

    public function save(array $values): array
    {
        $whatsapp = isset($values['whatsapp']) ? preg_replace('/\D/', '', (string) $values['whatsapp']) : '';
        // 01xxxxxxxxx (Egypt) → +201xxxxxxxxx; anything else is kept as typed with a +.
        $whatsapp = $whatsapp === '' ? null : '+'.(str_starts_with($whatsapp, '0') ? '20'.substr($whatsapp, 1) : $whatsapp);
        $message = trim((string) ($values['message'] ?? ''));
        DB::table('signup_settings')->updateOrInsert(['id' => 1], [
            'open' => (bool) $values['open'],
            'whatsapp' => $whatsapp,
            'message' => $message === '' || $message === self::DEFAULT_MESSAGE ? null : $message,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->cache->forget(self::CACHE);

        return $this->get();
    }
}
