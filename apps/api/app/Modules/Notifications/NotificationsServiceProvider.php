<?php

declare(strict_types=1);

namespace App\Modules\Notifications;

use App\Modules\Cash\Events\ShiftClosed;
use App\Modules\MultiBranch\Events\TransferShipped;
use App\Modules\Notifications\Console\GenerateVapidKeysCommand;
use App\Modules\Notifications\Console\SendDailySummariesCommand;
use App\Modules\Notifications\Contracts\Notifications;
use App\Modules\Notifications\Listeners\NotifyOnlineOrders;
use App\Modules\Notifications\Listeners\NotifyOwner;
use App\Modules\Notifications\Listeners\NotifyPartnerActivity;
use App\Modules\Notifications\Listeners\NotifyTransfers;
use App\Modules\Notifications\Support\Notifier;
use App\Modules\Notifications\Support\PushSender;
use App\Modules\Notifications\Support\WebPushSender;
use App\Modules\OnlineStore\Events\OnlineOrderPlaced;
use App\Modules\Sales\Events\SaleRefunded;
use App\Modules\ShopOrders\Events\ShopConnectionRequested;
use App\Modules\ShopOrders\Events\ShopOrderUpdated;
use App\Support\Modules\ModuleServiceProvider;

final class NotificationsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        ShopOrderUpdated::class => [NotifyPartnerActivity::class],
        ShopConnectionRequested::class => [NotifyPartnerActivity::class],
        ShiftClosed::class => [NotifyOwner::class],
        SaleRefunded::class => [NotifyOwner::class],
        OnlineOrderPlaced::class => [NotifyOnlineOrders::class],
        TransferShipped::class => [NotifyTransfers::class],
    ];

    public function register(): void
    {
        $this->app->bind(PushSender::class, WebPushSender::class);
        $this->app->bind(Notifications::class, Notifier::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([SendDailySummariesCommand::class, GenerateVapidKeysCommand::class]);
        }
    }
}
