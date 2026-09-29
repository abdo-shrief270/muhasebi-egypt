<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\ShopOrders\Actions\PlaceOrderAction;
use App\Modules\ShopOrders\Actions\TransitionOrderAction;
use App\Modules\ShopOrders\Contracts\PartnerRepairs;
use App\Modules\ShopOrders\Enums\OrderStatus;
use App\Modules\ShopOrders\Enums\OrderType;
use App\Modules\ShopOrders\Enums\Party;
use App\Modules\ShopOrders\Models\ShopOrder;
use App\Modules\ShopOrders\Models\ShopOrderItem;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleAccess;
use App\Support\Tenancy\CurrentTenant;

final class PartnerRepairsService implements PartnerRepairs
{
    /** The seller's path; progress() steps through it. */
    private const PATH = [OrderStatus::Accepted, OrderStatus::Preparing, OrderStatus::Ready, OrderStatus::Delivered];

    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly PlaceOrderAction $place,
        private readonly TransitionOrderAction $transition,
        private readonly ShopDirectory $shops,
        private readonly ModuleAccess $modules,
    ) {}

    public function send(string $partnerTenantId, string $userId, array $device, ?string $neededBy = null): array
    {
        $tenantId = $this->tenant->idOrFail();
        if (! $this->modules->enabled('shop_orders', $tenantId)) {
            throw new DomainRuleException('فعّل قسم «الطلبات بين المحلات» عشان تبعت أجهزة لمحل شريك.', 'module_not_enabled', 403);
        }

        $order = $this->place->handle($tenantId, $userId, $partnerTenantId, OrderType::Repair, [[
            'description' => $device['description'],
            'quantity' => 1,
            'device_model' => $device['device_model'],
            'imei' => $device['imei'],
            'note' => $device['note'],
        ]], $neededBy);

        return ['id' => $order->id, 'reference' => $order->reference(), 'shop_name' => $this->shops->find($partnerTenantId)?->name ?? ''];
    }

    public function received(string $orderId): ?array
    {
        $order = ShopOrder::query()->with('items')->find($orderId);
        if ($order === null || $order->type !== OrderType::Repair || $order->seller_tenant_id !== $this->tenant->id()) {
            return null;
        }

        return [
            'id' => $order->id,
            'reference' => $order->reference(),
            'from_tenant_id' => $order->buyer_tenant_id,
            'needed_by' => $order->needed_by?->toDateString(),
            'notes' => $order->notes,
            'items' => $order->items->map(fn (ShopOrderItem $i): array => [
                'id' => (int) $i->id,
                'description' => $i->description,
                'device_model' => $i->device_model,
                'imei' => $i->imei,
                'note' => $i->note,
            ])->values()->all(),
        ];
    }

    public function progress(string $orderId, string $userId, string $to): void
    {
        $order = ShopOrder::query()->find($orderId);
        $target = OrderStatus::tryFrom($to);
        if ($order === null || $target === null || $order->partyOf($this->tenant->idOrFail()) !== Party::Seller) {
            return;
        }

        $from = array_search($order->status, self::PATH, true);
        $until = array_search($target, self::PATH, true);
        if ($from === false || $until === false) {
            return; // not accepted yet, or already closed
        }

        for ($step = $from + 1; $step <= $until; $step++) {
            $this->transition->handle($order->seller_tenant_id, $userId, $order->id, self::PATH[$step], 'من تذكرة الصيانة');
        }
    }

    public function price(string $orderId, int $itemId, int $amount): void
    {
        $order = ShopOrder::query()->find($orderId);
        if ($order === null || $order->partyOf($this->tenant->idOrFail()) !== Party::Seller || $order->status->isFinal()) {
            return;
        }

        $order->items()->whereKey($itemId)->update(['unit_price' => $amount]);
        $order->total = (int) $order->items()->get()
            ->filter(fn (ShopOrderItem $i): bool => $i->unit_price !== null)
            ->sum(fn (ShopOrderItem $i): int => $i->unit_price * $i->quantity);
        $order->save();
    }
}
