<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager;

use App\Modules\ModuleManager\Actions\GrantModuleAction;
use App\Modules\ModuleManager\Actions\RevokeModuleAction;
use App\Modules\ModuleManager\Actions\StartModuleTrialAction;
use App\Modules\ModuleManager\Contracts\TenantModules;

final class TenantModuleService implements TenantModules
{
    public function __construct(
        private readonly StartModuleTrialAction $startTrial,
        private readonly GrantModuleAction $grant,
        private readonly RevokeModuleAction $revoke,
    ) {}

    public function startTrials(string $tenantId, array $keys): void
    {
        foreach ($keys as $key) {
            $this->startTrial->handle($tenantId, $key);
        }
    }

    public function grant(string $tenantId, string $key, string $source): void
    {
        $this->grant->handle($tenantId, $key, $source);
    }

    public function revoke(string $tenantId, string $key): void
    {
        $this->revoke->handle($tenantId, $key);
    }
}
