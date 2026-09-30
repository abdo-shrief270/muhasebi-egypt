<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns;

use App\Modules\Repairs\Events\DefectivePartRemoved;
use App\Modules\Sales\Events\SaleRefunded;
use App\Modules\SupplierReturns\Listeners\CollectDamagedSaleReturns;
use App\Modules\SupplierReturns\Listeners\CollectDefectiveRepairParts;
use App\Support\Modules\ModuleServiceProvider;

final class SupplierReturnsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        SaleRefunded::class => [CollectDamagedSaleReturns::class],
        DefectivePartRemoved::class => [CollectDefectiveRepairParts::class],
    ];
}
