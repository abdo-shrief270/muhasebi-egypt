<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Support\Modules\ModuleServiceProvider;

final class IdentityServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(ShopDirectory::class, ShopDirectoryService::class);
    }
}
