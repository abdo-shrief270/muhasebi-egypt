<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Contracts;

/**
 * Public API of the ModuleManager module for other modules (e.g. Identity at registration, Billing later).
 */
interface TenantModules
{
    /**
     * @param  list<string>  $keys
     */
    public function startTrials(string $tenantId, array $keys): void;

    public function grant(string $tenantId, string $key, string $source): void;

    public function revoke(string $tenantId, string $key): void;
}
