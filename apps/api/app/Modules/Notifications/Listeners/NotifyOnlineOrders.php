<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Notifications\Support\Notifier;
use App\Modules\OnlineStore\Events\OnlineOrderPlaced;
use App\Modules\OnlineStore\Events\RepairBookingPlaced;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/** A customer ordered (or booked a repair) on the shop's online store: the bell + a push to whoever handles orders. */
final class NotifyOnlineOrders extends ModuleListener
{
    protected function module(): string
    {
        return 'notifications';
    }

    protected function react(DomainEvent $event): void
    {
        if ($event instanceof RepairBookingPlaced) {
            app(Notifier::class)->notify(
                'repair_booking.placed',
                "حجز صيانة جديد {$event->reference}",
                $event->device.($event->preferredOn !== null ? ' · جاي يوم '.date('d/m', (int) strtotime($event->preferredOn)) : ''),
                'i-lucide-wrench',
                "/online-store/bookings?open={$event->bookingId}",
                'online_store.orders',
            );

            return;
        }
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
