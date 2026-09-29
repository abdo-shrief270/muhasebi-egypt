<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Actions;

use App\Modules\ShopOrders\Enums\OrderStatus;
use App\Modules\ShopOrders\Enums\Party;
use App\Modules\ShopOrders\Events\ShopOrderUpdated;
use App\Modules\ShopOrders\Models\ShopOrder;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Moves an order along its workflow. Each party may only make the moves OrderStatus::nextFor() allows.
 * When the seller accepts, it can price the items (piasters per unit).
 */
final class TransitionOrderAction
{
    public function __construct(
        private readonly EventRecorder $events,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array<int, int>  $unitPrices  item id => unit price in piasters
     */
    public function handle(string $tenantId, string $userId, string $orderId, OrderStatus $to, ?string $note = null, array $unitPrices = []): ShopOrder
    {
        return DB::transaction(function () use ($tenantId, $userId, $orderId, $to, $note, $unitPrices): ShopOrder {
            $order = ShopOrder::query()->lockForUpdate()->findOrFail($orderId);
            $party = $order->partyOf($tenantId);
            $from = $order->status;

            if (! in_array($to, $from->nextFor($party), true)) {
                throw new DomainRuleException(
                    "مينفعش الطلب يتحول من «{$from->label()}» لـ «{$to->label()}».",
                    'invalid_transition',
                    context: ['allowed' => array_map(fn (OrderStatus $s): string => $s->value, $from->nextFor($party))],
                );
            }

            if ($unitPrices !== [] && ! ($to === OrderStatus::Accepted && $party === Party::Seller)) {
                throw new DomainRuleException('الأسعار بيحطها المحل البايع وقت قبول الطلب.', 'prices_not_allowed');
            }

            if ($unitPrices !== []) {
                foreach ($order->items as $item) {
                    if (array_key_exists($item->id, $unitPrices)) {
                        $item->update(['unit_price' => $unitPrices[$item->id]]);
                    }
                }

                $order->total = $order->items()->get()
                    ->filter(fn ($item): bool => $item->unit_price !== null)
                    ->sum(fn ($item): int => $item->unit_price * $item->quantity);
            }

            $order->status = $to;
            if ($column = $to->timestampColumn()) {
                $order->{$column} = now();
            }
            $order->save();

            $parties = ['buyer_tenant_id' => $order->buyer_tenant_id, 'seller_tenant_id' => $order->seller_tenant_id];
            $order->activities()->create([
                ...$parties,
                'from_status' => $from,
                'to_status' => $to,
                'actor_party' => $party,
                'actor_user_id' => $userId,
                'note' => $note,
                'created_at' => now(),
            ]);

            $this->audit->record('shop_orders.'.$to->value, "حوّل الطلب {$order->reference()} لـ «{$to->label()}»", $order, tenantId: $tenantId);

            foreach ([$order->buyer_tenant_id => Party::Buyer, $order->seller_tenant_id => Party::Seller] as $partyTenant => $p) {
                $this->events->record(new ShopOrderUpdated(
                    tenantId: $partyTenant,
                    orderId: $order->id,
                    reference: $order->reference(),
                    party: $p->value,
                    fromStatus: $from->value,
                    toStatus: $to->value,
                    counterpartyTenantId: $order->counterpartyOf($partyTenant),
                    total: $order->total,
                    actorTenantId: $tenantId,
                    orderType: $order->type->value,
                ));
            }

            return $order;
        });
    }
}
