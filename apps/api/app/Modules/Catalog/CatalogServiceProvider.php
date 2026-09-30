<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Contracts\UsedDeviceCatalog;
use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Listeners\SeedDefaultCatalog;
use App\Modules\Identity\Events\TenantRegistered;
use App\Support\Modules\ModuleServiceProvider;

final class CatalogServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        TenantRegistered::class => [SeedDefaultCatalog::class],
    ];

    public function register(): void
    {
        $this->app->bind(VariantCatalog::class, VariantCatalogService::class);
        $this->app->bind(UsedDeviceCatalog::class, UsedDeviceCatalogService::class);
    }
}
