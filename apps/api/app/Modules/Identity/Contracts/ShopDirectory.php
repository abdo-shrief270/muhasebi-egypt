<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/**
 * Looks up shops across tenants. Only exact matches, so shops can't be browsed or enumerated.
 */
interface ShopDirectory
{
    public function findByCode(string $code): ?ShopSummary;

    public function find(string $tenantId): ?ShopSummary;

    /**
     * @param  list<string>  $tenantIds
     * @return array<string, ShopSummary> keyed by tenant id
     */
    public function findMany(array $tenantIds): array;
}
