<?php

declare(strict_types=1);

namespace App\Modules\Sales;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Sales\Contracts\CustomerSales;
use App\Modules\Sales\Contracts\UnitSales;
use App\Modules\Sales\Listeners\AnonymiseCustomerSales;
use App\Support\Modules\ModuleServiceProvider;

final class SalesServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        CustomerErased::class => [AnonymiseCustomerSales::class],
    ];

    public function register(): void
    {
        $this->app->bind(CustomerSales::class, CustomerSalesService::class);
        $this->app->bind(UnitSales::class, UnitSalesService::class);
    }
}
