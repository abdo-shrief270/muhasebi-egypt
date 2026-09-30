<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SaleRefunded;
use App\Modules\UsedDevices\Console\PurgeSellerIdsCommand;
use App\Modules\UsedDevices\Listeners\EraseSellerWithCustomer;
use App\Modules\UsedDevices\Listeners\SyncSoldDevices;
use App\Support\Modules\ModuleServiceProvider;

final class UsedDevicesServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        SaleCompleted::class => [SyncSoldDevices::class],
        SaleRefunded::class => [SyncSoldDevices::class],
        CustomerErased::class => [EraseSellerWithCustomer::class],
    ];

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([PurgeSellerIdsCommand::class]);
        }
    }
}
