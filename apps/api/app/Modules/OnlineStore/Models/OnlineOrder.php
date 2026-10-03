<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Models;

use App\Modules\OnlineStore\Enums\OrderStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * An order placed on the shop's online store (WEB-00001). Prices were the store's when it was
 * placed; it becomes an invoice at the POS («حوّل لفاتورة»), which closes it.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $branch_id
 * @property int $number
 * @property OrderStatus $status
 * @property string|null $customer_id
 * @property string $customer_name
 * @property string $customer_phone E.164
 * @property string $fulfilment pickup | delivery
 * @property string|null $zone_id
 * @property string|null $zone_name
 * @property string|null $address
 * @property string|null $notes
 * @property string $payment cod | transfer
 * @property string|null $proof
 * @property int $subtotal
 * @property int $delivery_fee
 * @property int $total
 * @property string $token
 * @property bool|null $consent
 * @property string|null $cancel_reason
 * @property string|null $sale_id
 * @property string|null $sale_reference
 * @property bool $fee_collected
 * @property Carbon $created_at
 * @property Carbon $updated_at
 */
#[Fillable([
    'tenant_id', 'branch_id', 'number', 'status', 'customer_id', 'customer_name', 'customer_phone', 'fulfilment',
    'zone_id', 'zone_name', 'address', 'notes', 'payment', 'proof', 'subtotal', 'delivery_fee', 'total', 'token',
    'consent', 'cancel_reason', 'sale_id', 'sale_reference', 'fee_collected',
])]
final class OnlineOrder extends Model
{
    use BelongsToTenant, HasUuids;

    public const PREFIX = 'WEB';

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'number' => 'integer',
            'subtotal' => 'integer',
            'delivery_fee' => 'integer',
            'total' => 'integer',
            'consent' => 'boolean',
            'fee_collected' => 'boolean',
        ];
    }

    /** @return HasMany<OnlineOrderItem, $this> */
    public function items(): HasMany
    {
        return $this->hasMany(OnlineOrderItem::class, 'order_id');
    }

    /** @return HasMany<OnlineOrderEvent, $this> */
    public function events(): HasMany
    {
        return $this->hasMany(OnlineOrderEvent::class, 'order_id')->orderBy('seq');
    }

    public function reference(): string
    {
        return self::PREFIX.'-'.str_pad((string) $this->number, 5, '0', STR_PAD_LEFT);
    }

    public function isDelivery(): bool
    {
        return $this->fulfilment === 'delivery';
    }

    /**
     * What the customer's tracking page shows: their order, never the shop's internals.
     *
     * @return array<string, mixed>
     */
    public function toPublic(): array
    {
        return [
            'reference' => $this->reference(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'customer_name' => $this->customer_name,
            'fulfilment' => $this->fulfilment,
            'zone_name' => $this->zone_name,
            'payment' => $this->payment,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'total' => $this->total,
            'cancel_reason' => $this->cancel_reason,
            'items' => $this->items->map(fn (OnlineOrderItem $i) => [
                'product_id' => $i->product_id, 'name' => $i->name, 'qty' => $i->qty, 'unit_price' => $i->unit_price, 'line_total' => $i->line_total,
            ])->values()->all(),
            'timeline' => $this->events->map(fn (OnlineOrderEvent $e) => [
                'status' => $e->status->value, 'label' => $e->status->label(), 'at' => $e->created_at->toIso8601String(),
            ])->values()->all(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }

    /**
     * The shop's view.
     *
     * @return array<string, mixed>
     */
    public function toApi(bool $full = false): array
    {
        $data = [
            'id' => $this->id,
            'number' => $this->number,
            'reference' => $this->reference(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'next' => array_map(fn (OrderStatus $s) => ['value' => $s->value, 'label' => $s->label()], $this->status->next($this->isDelivery())),
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'fulfilment' => $this->fulfilment,
            'zone_name' => $this->zone_name,
            'payment' => $this->payment,
            'has_proof' => $this->proof !== null,
            'subtotal' => $this->subtotal,
            'delivery_fee' => $this->delivery_fee,
            'total' => $this->total,
            'items_count' => $this->relationLoaded('items') ? $this->items->sum('qty') : null,
            'sale_id' => $this->sale_id,
            'sale_reference' => $this->sale_reference,
            'token' => $this->token,
            'created_at' => $this->created_at->toIso8601String(),
        ];
        if (! $full) {
            return $data;
        }

        return [
            ...$data,
            'branch_id' => $this->branch_id,
            'address' => $this->address,
            'notes' => $this->notes,
            'consent' => $this->consent,
            'cancel_reason' => $this->cancel_reason,
            'fee_collected' => $this->fee_collected,
            'items' => $this->items->map(fn (OnlineOrderItem $i) => [
                'id' => $i->id, 'product_id' => $i->product_id, 'variant_id' => $i->variant_id, 'name' => $i->name,
                'qty' => $i->qty, 'unit_price' => $i->unit_price, 'line_total' => $i->line_total,
            ])->values()->all(),
            'timeline' => $this->events->map(fn (OnlineOrderEvent $e) => [
                'status' => $e->status->value, 'label' => $e->status->label(), 'note' => $e->note, 'user_name' => $e->user_name, 'at' => $e->created_at->toIso8601String(),
            ])->values()->all(),
        ];
    }
}
