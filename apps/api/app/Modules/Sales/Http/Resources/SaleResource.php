<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Resources;

use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SalePayment;
use App\Modules\Sales\Models\SaleReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Money in piasters. Costs and profit only with $withCost (reports.profit).
 *
 * @mixin Sale
 */
final class SaleResource extends JsonResource
{
    public bool $withCost = false;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'number' => $this->number,
            'reference' => $this->reference(),
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'price_level' => $this->price_level->value,
            'price_level_label' => $this->price_level->label(),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'subtotal' => $this->subtotal,
            'discount' => $this->discount,
            'total' => $this->total,
            'paid' => $this->paid,
            'change' => $this->change,
            'refunded' => $this->refunded,
            'cost_total' => $this->when($this->withCost, $this->cost_total),
            'profit' => $this->when($this->withCost, fn () => ($this->total - $this->refunded) - ($this->cost_total - $this->refunded_cost)),
            'notes' => $this->notes,
            'cashier_name' => $this->cashier_name,
            'public_token' => $this->public_token,
            'completed_at' => $this->completed_at->toIso8601String(),
            'items_count' => $this->whenCounted('items'),
            'items' => $this->whenLoaded('items', fn () => $this->items->map(fn (SaleItem $i): array => [
                'id' => $i->id,
                'variant_id' => $i->variant_id,
                'name' => $i->name,
                'barcode' => $i->barcode,
                'qty' => $i->qty,
                'unit_price' => $i->unit_price,
                'discount' => $i->discount,
                'line_total' => $i->line_total,
                'returned_qty' => $i->returned_qty,
            ])->all()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (SalePayment $p): array => [
                'method' => $p->method->value,
                'method_label' => $p->method->label(),
                'amount' => $p->amount,
                'reference' => $p->reference,
            ])->all()),
            'returns' => $this->whenLoaded('returns', fn () => $this->returns->map(fn (SaleReturn $r): array => [
                'id' => $r->id,
                'reference' => $r->reference(),
                'total' => $r->total,
                'refund_method_label' => $r->refund_method->label(),
                'reason' => $r->reason,
                'created_by_name' => $r->created_by_name,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->all()),
        ];
    }

    public function withCost(bool $withCost): self
    {
        $this->withCost = $withCost;

        return $this;
    }
}
