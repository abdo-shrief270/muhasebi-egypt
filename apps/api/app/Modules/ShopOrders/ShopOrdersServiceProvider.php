<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders;

use App\Modules\ShopOrders\Contracts\PartnerRepairs;
use App\Support\Modules\ModuleServiceProvider;

final class ShopOrdersServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PartnerRepairs::class, PartnerRepairsService::class);
    }
}
