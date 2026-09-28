<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Actions;

use App\Modules\ShopOrders\Enums\ConnectionStatus;
use App\Modules\ShopOrders\Enums\OrderStatus;
use App\Modules\ShopOrders\Enums\OrderType;
use App\Modules\ShopOrders\Enums\Party;
use App\Modules\ShopOrders\Events\ShopOrderUpdated;
use App\Modules\ShopOrders\Models\ShopConnection;
use App\Modules\ShopOrders\Models\ShopOrder;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleAccess;
use Illuminate\Support\Facades\DB;

final class PlaceOrderAction
{
    public function __construct(
        private readonly ModuleAccess $modules,
        private readonly EventRecorder $events,
    ) {}

    /**
     * @param  list<array{description: string, quantity: int, device_model?: string|null, imei?: string|null, note?: string|null}>  $items
     */
    public function handle(
        string $buyerTenantId,
        string $userId,
        string $sellerTenantId,
        OrderType $type,
        array $items,
        ?string $neededBy = null,
        ?string $notes = null,
    ): ShopOrder {
        if (ShopConnection::between($buyerTenantId, $sellerTenantId)?->status !== ConnectionStatus::Accepted) {
            throw new DomainRuleException('لازم تكونوا شركاء الأول عشان تطلب من المحل ده.', 'not_partners', 403);
        }

        if (! $this->modules->enabled('shop_orders', $sellerTenantId)) {
            throw new DomainRuleException('المحل ده مش بيستقبل طلبات دلوقتي.', 'partner_module_disabled');
        }

        return DB::transaction(function () use ($buyerTenantId, $userId, $sellerTenantId, $type, $items, $neededBy, $notes): ShopOrder {
            $parties = ['buyer_tenant_id' => $buyerTenantId, 'seller_tenant_id' => $sellerTenantId];

            $order = ShopOrder::create([
                ...$parties,
                'number' => (int) DB::selectOne("select nextval('shop_order_number_seq') as n")->n,
                'type' => $type,
                'status' => OrderStatus::Placed,
                'needed_by' => $neededBy,
                'notes' => $notes,
                'placed_by' => $userId,
            ]);

            foreach ($items as $item) {
                $order->items()->create([...$parties, ...$item]);
            }

            $order->activities()->create([
                ...$parties,
                'from_status' => null,
                'to_status' => OrderStatus::Placed,
                'actor_party' => Party::Buyer,
                'actor_user_id' => $userId,
                'created_at' => now(),
            ]);

            foreach ([$buyerTenantId => Party::Buyer, $sellerTenantId => Party::Seller] as $tenantId => $party) {
                $this->events->record(new ShopOrderUpdated(
                    tenantId: $tenantId,
                    orderId: $order->id,
                    reference: $order->reference(),
                    party: $party->value,
                    fromStatus: null,
                    toStatus: OrderStatus::Placed->value,
                    counterpartyTenantId: $order->counterpartyOf($tenantId),
                    total: null,
                ));
            }

            return $order;
        });
    }
}
