<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Platform-wide billing switches platform admins edit (one billing_settings row; until saved, the
 * defaults of config/billing.php with the lifetime option off): yearly subscriptions and «مدى الحياة».
 */
final class BillingSettings
{
    private const CACHE = 'billing:settings:v1';

    public function __construct(private readonly Cache $cache) {}

    /**
     * @return array{yearly_enabled: bool, yearly_months: int, lifetime_enabled: bool, lifetime_name: string, lifetime_tagline: ?string, lifetime_installments: list<int>, lifetime_perks: list<string>, lifetime_affiliate_rate_percent: ?int, lifetime_allow_coupons: bool, launch_discount_percent: int, launch_seats: ?int, launch_ends_at: ?string}
     */
    public function get(): array
    {
        return $this->cache->rememberForever(self::CACHE, function (): array {
            $row = DB::table('billing_settings')->where('id', 1)->first();
            $d = config('billing.lifetime');
            if ($row === null) {
                return [
                    'yearly_enabled' => (bool) config('billing.yearly_enabled'),
                    'yearly_months' => (int) config('billing.yearly_months'),
                    'lifetime_enabled' => false,
                    'lifetime_name' => $d['name'],
                    'lifetime_tagline' => $d['tagline'],
                    'lifetime_installments' => array_map('intval', $d['installments']),
                    'lifetime_perks' => $d['perks'],
                    'lifetime_affiliate_rate_percent' => $d['affiliate_rate_percent'],
                    'lifetime_allow_coupons' => (bool) $d['allow_coupons'],
                    'launch_discount_percent' => 0,
                    'launch_seats' => null,
                    'launch_ends_at' => null,
                ];
            }

            return [
                'yearly_enabled' => (bool) $row->yearly_enabled,
                'yearly_months' => (int) $row->yearly_months,
                'lifetime_enabled' => (bool) $row->lifetime_enabled,
                'lifetime_name' => $row->lifetime_name,
                'lifetime_tagline' => $row->lifetime_tagline,
                'lifetime_installments' => array_values(array_map('intval', json_decode($row->lifetime_installments, true))),
                'lifetime_perks' => array_values(json_decode($row->lifetime_perks, true)),
                'lifetime_affiliate_rate_percent' => $row->lifetime_affiliate_rate_percent !== null ? (int) $row->lifetime_affiliate_rate_percent : null,
                'lifetime_allow_coupons' => (bool) $row->lifetime_allow_coupons,
                'launch_discount_percent' => (int) $row->launch_discount_percent,
                'launch_seats' => $row->launch_seats !== null ? (int) $row->launch_seats : null,
                'launch_ends_at' => $row->launch_ends_at !== null ? Carbon::parse($row->launch_ends_at)->toIso8601String() : null,
            ];
        });
    }

    /** @param array<string, mixed> $data validated by the admin controller */
    public function save(array $data): array
    {
        $values = [
            'yearly_enabled' => (bool) $data['yearly_enabled'],
            'yearly_months' => (int) $data['yearly_months'],
            'lifetime_enabled' => (bool) $data['lifetime_enabled'],
            'lifetime_name' => $data['lifetime_name'],
            'lifetime_tagline' => $data['lifetime_tagline'] ?? null,
            'lifetime_installments' => json_encode(array_values(array_unique(array_map('intval', $data['lifetime_installments'])))),
            'lifetime_perks' => json_encode(array_values(array_filter(array_map(fn (?string $p) => trim((string) $p), $data['lifetime_perks'] ?? [])))),
            'lifetime_affiliate_rate_percent' => $data['lifetime_affiliate_rate_percent'] ?? null,
            'lifetime_allow_coupons' => (bool) ($data['lifetime_allow_coupons'] ?? false),
            'launch_discount_percent' => (int) ($data['launch_discount_percent'] ?? 0),
            'launch_seats' => $data['launch_seats'] ?? null,
            // A date alone = through the end of that day in Cairo.
            'launch_ends_at' => isset($data['launch_ends_at']) ? (strlen((string) $data['launch_ends_at']) === 10 ? Carbon::parse($data['launch_ends_at'], 'Africa/Cairo')->endOfDay()->utc() : Carbon::parse($data['launch_ends_at'])) : null,
            'updated_at' => now(),
        ];
        DB::table('billing_settings')->updateOrInsert(['id' => 1], $values + ['created_at' => now()]);
        $this->cache->forget(self::CACHE);

        return $this->get();
    }
}
