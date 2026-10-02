<?php

declare(strict_types=1);

namespace App\Modules\OwnerApp;

use App\Modules\Cash\Events\ShiftClosed;
use App\Modules\OwnerApp\Contracts\Approvals;
use App\Modules\OwnerApp\Listeners\PingOwnerScreens;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SaleRefunded;
use App\Support\Modules\ModuleServiceProvider;

final class OwnerAppServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        SaleCompleted::class => [PingOwnerScreens::class],
        SaleRefunded::class => [PingOwnerScreens::class],
        ShiftClosed::class => [PingOwnerScreens::class],
    ];

    public function register(): void
    {
        $this->app->bind(Approvals::class, ApprovalsService::class);
    }
}
