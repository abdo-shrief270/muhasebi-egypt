<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Listeners;

use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Repairs\Actions\ReceiveDeviceAction;
use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Support\Timeline;
use App\Modules\ShopOrders\Contracts\PartnerRepairs;
use App\Modules\ShopOrders\Events\ShopOrderUpdated;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/**
 * Repair orders between partner shops, on each shop's side:
 *  - the shop that sent a device: the partner's progress (and price) shows on its ticket;
 *  - the partner: accepting the order opens a ticket per device, the sending shop as the customer.
 */
final class SyncPartnerRepairs extends ModuleListener
{
    private const LABELS = [
        'placed' => 'الطلب اتبعت',
        'accepted' => 'قبل الطلب',
        'preparing' => 'بيشتغل عليه',
        'ready' => 'الجهاز جاهز عنده',
        'delivered' => 'رجّع الجهاز',
        'completed' => 'الطلب اتقفل',
        'rejected' => 'رفض الطلب',
        'cancelled' => 'الطلب اتلغى',
    ];

    protected function module(): string
    {
        return 'repairs';
    }

    protected function shouldReact(DomainEvent $event): bool
    {
        return $event instanceof ShopOrderUpdated;
    }

    protected function react(DomainEvent $event): void
    {
        /** @var ShopOrderUpdated $event */
        $event->party === 'buyer' ? $this->onSentOrder($event) : $this->onReceivedOrder($event);
    }

    private function onSentOrder(ShopOrderUpdated $event): void
    {
        $ticket = RepairTicket::query()->where('outsourced_order_id', $event->orderId)->lockForUpdate()->first();
        if ($ticket === null || $event->fromStatus === null) {
            return;
        }

        $ticket->outsourced_status = $event->toStatus;
        if ($event->total !== null) {
            $ticket->outsource_cost = $event->total;
        }
        $ticket->save();

        $note = self::LABELS[$event->toStatus] ?? $event->toStatus;
        if ($event->total !== null && in_array($event->toStatus, ['accepted', 'delivered'], true)) {
            $note .= ' — الحساب '.number_format($event->total / 100, 2).' ج';
        }
        app(Timeline::class)->add($ticket, EventType::Partner, "«{$ticket->outsourced_shop}»: {$note}");
    }

    private function onReceivedOrder(ShopOrderUpdated $event): void
    {
        if ($event->toStatus !== 'accepted' || RepairTicket::query()->where('partner_order_id', $event->orderId)->exists()) {
            return;
        }
        $order = app(PartnerRepairs::class)->received($event->orderId);
        $branchId = app(BranchDirectory::class)->mainBranchId();
        $shop = app(ShopDirectory::class)->find($event->counterpartyTenantId);
        if ($order === null || $branchId === null || $shop === null) {
            return;
        }

        foreach ($order['items'] as $item) {
            $ticket = app(ReceiveDeviceAction::class)->handle($event->tenantId, $branchId, [
                'customer_name' => $shop->name,
                'customer_phone' => $shop->phone,
                'device_name' => $item['device_model'] ?: $item['description'],
                'imei' => $item['imei'],
                'reported_note' => implode(' — ', array_filter([$item['description'], $item['note'], $order['notes']])),
                'expected_at' => $order['needed_by'],
            ], []);
            $ticket->fill(['partner_order_id' => $order['id'], 'partner_item_id' => $item['id'], 'partner_reference' => $order['reference']])->save();
            app(Timeline::class)->add($ticket, EventType::Partner, "جاي من «{$shop->name}» — طلب {$order['reference']}");
        }
    }
}
