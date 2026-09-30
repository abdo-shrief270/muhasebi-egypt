<?php

declare(strict_types=1);

namespace App\Modules\Suppliers;

use App\Modules\Suppliers\Contracts\SupplierAccounts;
use App\Support\Modules\ModuleServiceProvider;

final class SuppliersServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SupplierAccounts::class, SupplierAccountsService::class);
    }
}
