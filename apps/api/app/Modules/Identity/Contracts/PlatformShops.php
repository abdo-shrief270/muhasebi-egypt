<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

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
     * @return array<string, array{id: string, name: string, code: string, phone: string, types: string, owner_name: string|null, owner_phone: string|null, users: int, branches: int, created_at: string}>
     */
    public function details(array $tenantIds): array;
}
