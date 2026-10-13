<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

use Illuminate\Support\Carbon;

/**
 * Every shop on the platform, for the platform's own admins (never exposed to shops).
 */
interface PlatformShops
{
    /**
     * @return list<string> ids of shops whose name, code, phone or owner's phone matches
     */
    public function searchIds(string $q, int $limit = 200): array;

    /**
     * @param  list<string>  $tenantIds
     * @return array<string, array{id: string, name: string, code: string, phone: string, types: string, owner_name: string|null, owner_phone: string|null, users: int, branches: int, created_at: string, last_sign_in_at: string|null, last_seen_at: string|null, acquisition: array<string, string>|null}> last sign-in / last request of any of the shop's users (signed-in devices); acquisition = the campaign it signed up from
     */
    public function details(array $tenantIds): array;

    /**
     * Shops registered since then, grouped by the campaign they came from (source `direct` = none).
     *
     * @return list<array{source: string, medium: string, campaign: string, shops: int, tenant_ids: list<string>}>
     */
    public function signupsBySource(Carbon $since): array;

    /**
     * A shop the team opens by hand (self sign-up closed, the owner asked on WhatsApp): registered
     * like any other — trials, default catalog, TenantRegistered — with a password made here for the
     * owner to sign in with and change.
     *
     * @param  list<string>  $shopTypes  ShopType values
     * @param  list<string>|null  $needs  Billing's need keys, null = by shop type
     * @param  array<string, string>  $acquisition  where the owner came from (source, campaign…)
     * @return array{tenant_id: string, owner_id: string, phone: string, password: string}
     */
    public function open(string $shopName, string $ownerName, string $phone, array $shopTypes, ?array $needs, array $acquisition): array;
}
