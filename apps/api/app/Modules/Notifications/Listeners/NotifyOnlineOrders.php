<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Support\Notifier;
use App\Modules\OnlineStore\Events\OnlineOrderPlaced;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/** A customer ordered on the shop's online store: the bell + a push to whoever handles orders. */
final class NotifyOnlineOrders extends ModuleListener
{
    protected function module(): string
    {
        return 'notifications';
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof OnlineOrderPlaced);

        app(Notifier::class)->notify(
            'online_order.placed',
            "طلب أونلاين جديد {$event->reference}",
            $event->items.' قطعة · '.number_format($event->total / 100, 2).' ج · '.($event->fulfilment === 'delivery' ? 'توصيل' : 'استلام من المحل'),
            'i-lucide-shopping-bag',
            "/online-store/orders/{$event->orderId}",
            'online_store.orders',
        );
    }
}
