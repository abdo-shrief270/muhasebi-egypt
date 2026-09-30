<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts;

/**
 * Whether shops actually work in the app (for the platform admins during the beta).
 */
interface ShopActivity
{
    /**
     * @param  list<string>  $tenantIds
     * @return array<string, array{last_sale_at: string|null, sales_7d: int, repairs_7d: int}>
     */
    public function recent(array $tenantIds): array;
}
