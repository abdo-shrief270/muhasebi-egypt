<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\OnlineStore\Contracts\OnlineOrders;
use App\Modules\OnlineStore\Contracts\OrderForSale;
use App\Modules\OnlineStore\Enums\OrderStatus;
use App\Modules\OnlineStore\Models\OnlineOrder;
use App\Modules\OnlineStore\Models\OnlineOrderItem;
use App\Modules\OnlineStore\Support\OrderTimeline;
use App\Support\Exceptions\DomainRuleException;

final class OnlineOrdersService implements OnlineOrders
{
    public function __construct(
        private readonly CashDrawer $drawer,
        private readonly OrderTimeline $timeline,
    ) {}

    public function forSale(string $orderId): OrderForSale
    {
        $order = OnlineOrder::query()->with('items')->find($orderId)
            ?? throw new DomainRuleException('الطلب الأونلاين ده مش موجود.', 'online_order_not_found', 404);
        $this->ensureOpen($order);

        return new OrderForSale(
            id: $order->id,
            reference: $order->reference(),
            prices: $order->items->mapWithKeys(fn (OnlineOrderItem $i) => [$i->variant_id => $i->unit_price])->all(),
            deliveryFee: $order->delivery_fee,
        );
    }

    public function invoiced(string $orderId, string $saleId, string $saleReference, string $branchId, bool $feeCollected): void
    {
        $order = OnlineOrder::query()->lockForUpdate()->find($orderId)
            ?? throw new DomainRuleException('الطلب الأونلاين ده مش موجود.', 'online_order_not_found', 404);
        $this->ensureOpen($order);

        $collect = $feeCollected && $order->delivery_fee > 0;
        $order->update([
            'status' => OrderStatus::Delivered,
            'sale_id' => $saleId,
            'sale_reference' => $saleReference,
            'fee_collected' => $collect,
        ]);
        if ($collect) {
            $this->drawer->record($branchId, DrawerEntry::Delivery, 'cash', $order->delivery_fee, 'online_order', $order->id, "توصيل {$order->reference()}", requireShift: true);
        }
        $this->timeline->add($order, OrderStatus::Delivered, "اتعملت الفاتورة {$saleReference}");
    }

    private function ensureOpen(OnlineOrder $order): void
    {
        if ($order->sale_id !== null) {
            throw new DomainRuleException("الطلب {$order->reference()} اتعمله فاتورة قبل كده ({$order->sale_reference}).", 'online_order_invoiced');
        }
        if (! $order->status->isOpen()) {
            throw new DomainRuleException("الطلب {$order->reference()} {$order->status->label()}.", 'online_order_closed');
        }
    }
}
