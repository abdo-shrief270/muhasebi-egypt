<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Notifications\Support\Notifier;
use App\Modules\ShopOrders\Events\ShopConnectionRequested;
use App\Modules\ShopOrders\Events\ShopOrderUpdated;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/**
 * What partner shops did that this shop should know about: a new order, a partnership request,
 * the other side moving an order (repair orders included — the ticket follows the order). Only
 * the other shop's doing: a shop isn't told about its own changes.
 */
final class NotifyPartnerActivity extends ModuleListener
{
    protected function module(): string
    {
        return 'notifications';
    }

    protected function shouldReact(DomainEvent $event): bool
    {
        return $event instanceof ShopConnectionRequested
            || ($event instanceof ShopOrderUpdated && $this->actorOf($event) !== $event->tenantId);
    }

    protected function react(DomainEvent $event): void
    {
        if ($event instanceof ShopConnectionRequested) {
            $shop = $this->shopName($event->requesterTenantId);
            $this->add('shop_connection.requested', "طلب شراكة من «{$shop}»", 'وافق عشان تقدروا تطلبوا من بعض.', 'i-lucide-user-plus', '/shop-orders/partners', 'shop_orders.partners');

            return;
        }

        /** @var ShopOrderUpdated $event */
        $shop = $this->shopName($event->counterpartyTenantId);
        $what = $event->orderType === 'repair' ? 'شغل الصيانة' : 'الطلب';
        $ref = $event->reference;

        [$title, $icon] = match ($event->toStatus) {
            'placed' => [$event->orderType === 'repair' ? "شغل صيانة جديد من «{$shop}»" : "طلب جديد من «{$shop}»", 'i-lucide-inbox'],
            'accepted' => ["«{$shop}» قبل {$what} {$ref}", 'i-lucide-circle-check'],
            'rejected' => ["«{$shop}» رفض {$what} {$ref}", 'i-lucide-circle-x'],
            'preparing' => ["«{$shop}» بدأ يجهّز {$what} {$ref}", 'i-lucide-hammer'],
            'ready' => ["{$what} {$ref} جاهز عند «{$shop}»", 'i-lucide-package-check'],
            'delivered' => ["«{$shop}» سلّم {$what} {$ref}", 'i-lucide-truck'],
            'completed' => ["«{$shop}» أكّد استلام {$what} {$ref}", 'i-lucide-check-check'],
            'cancelled' => ["«{$shop}» لغى {$what} {$ref}", 'i-lucide-ban'],
            default => ["{$what} {$ref} اتحدّث عند «{$shop}»", 'i-lucide-handshake'],
        };
        $body = match (true) {
            $event->toStatus === 'placed' => $ref,
            $event->total !== null && in_array($event->toStatus, ['accepted', 'delivered'], true) => 'الحساب '.number_format($event->total / 100, 2).' ج',
            default => null,
        };

        $this->add("shop_order.{$event->toStatus}", $title, $body, $icon, "/shop-orders/{$event->orderId}", 'shop_orders.view');
    }

    /** Who made the change; events from before it was recorded: only a new order is known to be the buyer's. */
    private function actorOf(ShopOrderUpdated $event): ?string
    {
        if ($event->actorTenantId !== null) {
            return $event->actorTenantId;
        }
        if ($event->fromStatus === null) {
            return $event->party === 'buyer' ? $event->tenantId : $event->counterpartyTenantId;
        }

        return null;
    }

    private function shopName(string $tenantId): string
    {
        return app(ShopDirectory::class)->find($tenantId)->name ?? 'محل شريك';
    }

    private function add(string $type, string $title, ?string $body, string $icon, string $to, string $permission): void
    {
        app(Notifier::class)->notify($type, $title, $body, $icon, $to, $permission);
    }
}
