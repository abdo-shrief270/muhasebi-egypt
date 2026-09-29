<?php

declare(strict_types=1);

namespace App\Modules\Customers;

use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Support\Modules\ModuleServiceProvider;

final class CustomersServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CustomerAccounts::class, CustomerAccountsService::class);
    }
}
