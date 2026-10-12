<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Billing\Contracts\SubscriptionStanding;
use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Marketplace\Models\MarketSettings;
use App\Modules\OnlineStore\Contracts\PublicStores;
use App\Modules\OnlineStore\Contracts\ShopReputation;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Facades\Cache;

/**
 * «احجز صيانة» in the marketplace: listed shops whose own store takes repair bookings, one row per
 * branch with its place, nearest first. The booking itself goes to the shop's store endpoint.
 */
final class RepairShops
{
    public function __construct(
        private readonly PublicStores $stores,
        private readonly SubscriptionStanding $standing,
        private readonly BranchDirectory $branches,
        private readonly ShopDirectory $shops,
        private readonly ShopReputation $reputation,
        private readonly CurrentTenant $tenant,
    ) {}

    /** @return list<array<string, mixed>> */
    public function near(?float $lat, ?float $lng, ?string $governorate, int $limit = 30): array
    {
        $rows = array_filter($this->all(), fn (array $r) => $governorate === null || $r['governorate'] === $governorate);
        foreach ($rows as &$row) {
            $row['distance_km'] = $lat !== null && $lng !== null && $row['location'] !== null
                ? round(self::km([$lat, $lng], [$row['location']['lat'], $row['location']['lng']]), 1) : null;
        }
        unset($row);
        usort($rows, fn (array $a, array $b) => $lat !== null
            ? [($a['distance_km'] ?? INF), -($a['rating'] ?? 0)] <=> [($b['distance_km'] ?? INF), -($b['rating'] ?? 0)]
            : [-($a['rating'] ?? 0), $a['name']] <=> [-($b['rating'] ?? 0), $b['name']]);

        return array_slice(array_values($rows), 0, $limit);
    }

    /** @return list<array<string, mixed>> every branch that takes bookings (cached a minute) */
    private function all(): array
    {
        return Cache::remember('market:repair-shops:v1', 60, function (): array {
            $stores = $this->stores->repairShops();
            $scores = $this->reputation->scores(array_keys($stores));
            $rows = [];
            foreach ($stores as $tenantId => $store) {
                if (! $this->standing->listed($tenantId) || ! $this->tenant->runAs($tenantId, fn () => MarketSettings::current()->listed)) {
                    continue;
                }
                $shop = $this->shops->find($tenantId);
                if ($shop === null) {
                    continue;
                }
                $hidden = $this->tenant->runAs($tenantId, fn () => array_flip(MarketSettings::current()->hidden_branches));
                foreach ($this->tenant->runAs($tenantId, fn () => $this->branches->locations()) as $branchId => $b) {
                    if (isset($hidden[$branchId])) {
                        continue;
                    }
                    $rows[] = [
                        'slug' => strtolower($shop->code),
                        'name' => $shop->name,
                        'store' => $store['slug'],
                        'note' => $store['note'],
                        'branch_id' => $branchId,
                        'branch' => $b['name'],
                        'address' => $b['address'],
                        'governorate' => $b['governorate'],
                        'governorate_label' => $b['governorate_label'],
                        'area' => $b['area'],
                        'location' => $b['latitude'] !== null && $b['longitude'] !== null ? ['lat' => $b['latitude'], 'lng' => $b['longitude']] : null,
                        'rating' => $scores[$tenantId]['rating'] ?? null,
                        'reviews' => $scores[$tenantId]['reviews'] ?? 0,
                    ];
                }
            }

            return $rows;
        });
    }

    /** @param array{0: float, 1: float} $a @param array{0: float, 1: float} $b */
    private static function km(array $a, array $b): float
    {
        [$lat1, $lng1, $lat2, $lng2] = array_map('deg2rad', [$a[0], $a[1], $b[0], $b[1]]);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($h)));
    }
}
