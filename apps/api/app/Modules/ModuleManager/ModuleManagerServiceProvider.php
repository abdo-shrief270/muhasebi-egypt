<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager;

use App\Modules\ModuleManager\Contracts\PlatformModules;
use App\Modules\ModuleManager\Contracts\TenantModules;
use App\Modules\ModuleManager\Support\PlatformSettings;
use App\Support\Modules\FeatureAccess;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleRegistry;
use App\Support\Modules\ModuleServiceProvider;

final class ModuleManagerServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->scoped(ModuleAccess::class, ModuleGate::class);
        $this->app->scoped(FeatureAccess::class, FeatureGate::class);
        $this->app->bind(TenantModules::class, TenantModuleService::class);
        $this->app->bind(PlatformModules::class, PlatformSettings::class);
    }

    public function boot(): void
    {
        parent::boot();
        // The platform admin's changes for every shop (status, trial, feature switches).
        $this->app->make(ModuleRegistry::class)->useOverrides(fn (): array => $this->app->make(PlatformSettings::class)->overrides());
    }
}
