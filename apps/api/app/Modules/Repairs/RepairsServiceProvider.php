<?php

declare(strict_types=1);

namespace App\Modules\Repairs;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\ModuleManager\Events\ModuleEnabled;
use App\Modules\Repairs\Contracts\CustomerRepairs;
use App\Modules\Repairs\Listeners\AnonymiseCustomerTickets;
use App\Modules\Repairs\Listeners\SeedDefaultFaults;
use App\Modules\Repairs\Listeners\SyncPartnerRepairs;
use App\Modules\ShopOrders\Events\ShopOrderUpdated;
use App\Support\Modules\ModuleServiceProvider;

final class RepairsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        ModuleEnabled::class => [SeedDefaultFaults::class],
        ShopOrderUpdated::class => [SyncPartnerRepairs::class],
        CustomerErased::class => [AnonymiseCustomerTickets::class],
    ];

    public function register(): void
    {
        $this->app->bind(CustomerRepairs::class, CustomerRepairsService::class);
    }
}
