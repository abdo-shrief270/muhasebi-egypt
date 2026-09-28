<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Listeners\SeedDefaultCatalog;
use App\Modules\Identity\Events\TenantRegistered;
use App\Support\Modules\ModuleServiceProvider;

final class CatalogServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        TenantRegistered::class => [SeedDefaultCatalog::class],
    ];
}
