<?php

declare(strict_types=1);

namespace App\Support\Modules;

/**
 * Answers "can this shop use this module right now?". Implemented by the ModuleManager module.
 */
interface ModuleAccess
{
    public function state(string $key, ?string $tenantId = null): ModuleState;

    public function enabled(string $key, ?string $tenantId = null): bool;

    /**
     * @return list<string>
     */
    public function enabledKeys(?string $tenantId = null): array;

    public function forget(string $tenantId): void;
}
