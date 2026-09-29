<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Notifications\Listeners\NotifyPartnerActivity;
use App\Modules\ShopOrders\Events\ShopConnectionRequested;
use App\Modules\ShopOrders\Events\ShopOrderUpdated;
use App\Support\Modules\ModuleServiceProvider;

final class NotificationsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        ShopOrderUpdated::class => [NotifyPartnerActivity::class],
        ShopConnectionRequested::class => [NotifyPartnerActivity::class],
    ];
}
