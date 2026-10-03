<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Actions;

use App\Modules\Catalog\Contracts\StorefrontCatalog;
use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\OnlineStore\Enums\OrderStatus;
use App\Modules\OnlineStore\Events\OnlineOrderPlaced;
use App\Modules\OnlineStore\Models\DeliveryZone;
use App\Modules\OnlineStore\Models\OnlineOrder;
use App\Modules\OnlineStore\Models\OnlineStore;
use App\Modules\OnlineStore\Support\OrderTimeline;
use App\Modules\OnlineStore\Support\ProofStore;
use App\Modules\OnlineStore\Support\Storefront;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * A customer orders on the store. Prices, the delivery fee and what's available come from the
 * server (never the cart); nothing leaves stock until the shop makes the invoice. With the
 * customer's consent they become (or are matched to) a customer of the shop.
 */
final class PlaceOrderAction
{
    /** Orders still «جديد» from one phone before the store says "wait for the shop to call". */
    public const MAX_NEW_PER_PHONE = 3;

    public function __construct(
        private readonly StorefrontCatalog $catalog,
        private readonly Storefront $storefront,
        private readonly CustomerAccounts $customers,
        private readonly DocumentNumbers $numbers,
        private readonly OrderTimeline $timeline,
        private readonly ProofStore $proofs,
        private readonly EventRecorder $events,
    ) {}

    /**
     * @param  list<array{variant_id: string, qty: int}>  $items
     */
    public function handle(
        OnlineStore $store,
        ?string $orderId,
        string $name,
        string $phone,
        string $fulfilment,
        ?string $zoneId,
        ?string $address,
        ?string $notes,
        string $payment,
        ?UploadedFile $proof,
        bool $consent,
        array $items,
    ): OnlineOrder {
        // The same checkout sent twice (a retry after a timeout) is saved once.
        if ($orderId !== null && ($existing = OnlineOrder::query()->find($orderId)) !== null) {
            return $existing;
        }
        if (! $store->takesOrders()) {
            throw new DomainRuleException('المتجر ده بيستقبل الطلبات على واتساب بس.', 'store_not_taking_orders', 409);
        }
        if ($fulfilment === 'pickup' && ! $store->pickup || $fulfilment === 'delivery' && ! $store->delivery) {
            throw new DomainRuleException($fulfilment === 'pickup' ? 'المحل مش بيسلّم من عنده دلوقتي.' : 'المحل مش بيوصّل دلوقتي.', 'fulfilment_unavailable');
        }
        if ($payment === 'cod' && ! $store->pay_cod || $payment === 'transfer' && ! $store->pay_transfer) {
            throw new DomainRuleException('طريقة الدفع دي مش متاحة في المتجر.', 'payment_unavailable');
        }
        if ($payment === 'transfer' && $proof === null) {
            throw new DomainRuleException('ارفع صورة التحويل.', 'proof_required');
        }
        $zone = null;
        if ($fulfilment === 'delivery') {
            $zone = $zoneId === null ? null : DeliveryZone::query()->where('is_active', true)->find($zoneId);
            if ($zone === null) {
                throw new DomainRuleException('اختار منطقة التوصيل.', 'zone_required');
            }
            if (trim((string) $address) === '') {
                throw new DomainRuleException('اكتب العنوان بالتفصيل.', 'address_required');
            }
        }
        if (OnlineOrder::query()->where('customer_phone', $phone)->where('status', OrderStatus::New)->count() >= self::MAX_NEW_PER_PHONE) {
            throw new DomainRuleException('عندك طلبات لسه المحل ماأكدهاش. استنى مكالمة المحل أو كلّمه على واتساب.', 'too_many_orders', 429);
        }

        $ids = array_column($items, 'variant_id');
        $variants = $this->catalog->variants($ids);
        $missing = array_values(array_diff($ids, array_keys($variants)));
        if ($missing !== []) {
            throw new DomainRuleException('فيه أصناف في السلة مابقتش موجودة في المتجر. شيلها وكمّل.', 'items_unavailable', context: ['variant_ids' => $missing]);
        }
        $stock = $this->storefront->quantities($store, $ids);
        $short = array_values(array_filter($items, fn (array $i) => $i['qty'] > max(0, $stock[$i['variant_id']] ?? 0)));
        if ($short !== []) {
            $names = implode('، ', array_map(fn (array $i) => $variants[$i['variant_id']]['name'], $short));
            throw new DomainRuleException("«{$names}» مش متوفر بالكمية دي دلوقتي.", 'out_of_stock', context: [
                'items' => array_map(fn (array $i) => ['variant_id' => $i['variant_id'], 'available' => max(0, $stock[$i['variant_id']] ?? 0)], $short),
            ]);
        }

        $lines = array_map(fn (array $i) => [
            'variant' => $variants[$i['variant_id']],
            'qty' => $i['qty'],
            'line_total' => $variants[$i['variant_id']]['price'] * $i['qty'],
        ], $items);
        $subtotal = array_sum(array_column($lines, 'line_total'));
        if ($subtotal < $store->min_order) {
            throw new DomainRuleException('أقل طلب '.number_format($store->min_order / 100, 2).' ج.', 'below_min_order', context: ['min_order' => $store->min_order]);
        }
        $fee = $zone === null || ($store->free_delivery_over !== null && $subtotal >= $store->free_delivery_over) ? 0 : $zone->fee;

        $id = $orderId ?? (string) Str::uuid7();
        $proofName = $proof !== null ? $this->proofs->put($store->tenant_id, $id, $proof) : null;

        try {
            return DB::transaction(function () use ($store, $id, $name, $phone, $fulfilment, $zone, $address, $notes, $payment, $proofName, $consent, $lines, $subtotal, $fee): OnlineOrder {
                $customer = $consent ? $this->customers->findOrCreate($name, $phone, true) : null;
                $order = new OnlineOrder([
                    'tenant_id' => $store->tenant_id,
                    'branch_id' => $this->storefront->branch($store),
                    'number' => $this->numbers->next($store->tenant_id, 'online_order'),
                    'status' => OrderStatus::New,
                    'customer_id' => $customer?->id,
                    'customer_name' => $name,
                    'customer_phone' => $phone,
                    'fulfilment' => $fulfilment,
                    'zone_id' => $zone?->id,
                    'zone_name' => $zone?->name,
                    'address' => $fulfilment === 'delivery' ? $address : null,
                    'notes' => $notes,
                    'payment' => $payment,
                    'proof' => $proofName,
                    'subtotal' => $subtotal,
                    'delivery_fee' => $fee,
                    'total' => $subtotal + $fee,
                    'token' => Str::random(32),
                    'consent' => $consent,
                ]);
                $order->id = $id;
                $order->save();
                foreach ($lines as $line) {
                    $order->items()->create([
                        'tenant_id' => $store->tenant_id,
                        'product_id' => $line['variant']['product_id'],
                        'variant_id' => $line['variant']['id'],
                        'name' => $line['variant']['name'],
                        'qty' => $line['qty'],
                        'unit_price' => $line['variant']['price'],
                        'line_total' => $line['line_total'],
                    ]);
                }
                $this->timeline->add($order, OrderStatus::New);
                $this->events->record(new OnlineOrderPlaced(
                    tenantId: $store->tenant_id,
                    orderId: $order->id,
                    reference: $order->reference(),
                    total: $order->total,
                    items: array_sum(array_column($lines, 'qty')),
                    fulfilment: $fulfilment,
                ));

                return $order;
            });
        } catch (UniqueConstraintViolationException $e) {
            // The same id arrived twice at once: the other request saved it.
            return OnlineOrder::query()->find($id) ?? throw $e;
        }
    }
}
