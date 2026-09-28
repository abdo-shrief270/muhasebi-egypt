<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager;

use App\Modules\ModuleManager\Contracts\TenantModules;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleServiceProvider;

final class ModuleManagerServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ModuleAccess::class, ModuleGate::class);
        $this->app->bind(TenantModules::class, TenantModuleService::class);
    }
}
