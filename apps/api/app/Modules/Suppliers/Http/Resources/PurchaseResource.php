<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Resources;

use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Suppliers\Models\Purchase;
use App\Modules\Suppliers\Models\PurchaseItem;
use App\Modules\Suppliers\Models\PurchaseReturn;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Needs ['purchase' => Purchase, 'variants' => array<string, VariantSummary>] when items are shown.
 *
 * @property array{purchase: Purchase, variants?: array<string, VariantSummary>} $resource
 */
final class PurchaseResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $p = $this->resource['purchase'];
        $variants = $this->resource['variants'] ?? [];

        return [
            'id' => $p->id,
            'number' => $p->number,
            'reference' => $p->reference(),
            'supplier' => $p->relationLoaded('supplier') ? ['id' => $p->supplier->id, 'name' => $p->supplier->name] : null,
            'supplier_invoice_no' => $p->supplier_invoice_no,
            'invoice_date' => $p->invoice_date->toDateString(),
            'subtotal' => $p->subtotal,
            'discount' => $p->discount,
            'total' => $p->total,
            'paid' => $p->paid,
            'payment_method' => $p->payment_method?->value,
            'payment_method_label' => $p->payment_method?->label(),
            'returned' => $p->returned,
            'notes' => $p->notes,
            'created_by_name' => $p->created_by_name,
            'created_at' => $p->created_at?->toIso8601String(),
            'items_count' => $p->relationLoaded('items') ? $p->items->count() : null,
            'items' => $p->relationLoaded('items') ? $p->items->map(fn (PurchaseItem $i): array => [
                'id' => $i->id,
                'variant_id' => $i->variant_id,
                'name' => isset($variants[$i->variant_id]) ? $variants[$i->variant_id]->displayName() : null,
                'barcode' => isset($variants[$i->variant_id]) ? $variants[$i->variant_id]->barcode : null,
                'qty' => $i->qty,
                'unit_cost' => $i->unit_cost,
                'net_unit_cost' => $i->net_unit_cost,
                'line_total' => $i->line_total,
                'previous_cost' => $i->previous_cost,
                'cost_increased' => $i->costIncreased(),
                'returned_qty' => $i->returned_qty,
                'serials' => $i->serials,
            ])->all() : [],
            'returns' => $p->relationLoaded('returns') ? $p->returns->map(fn (PurchaseReturn $r): array => [
                'id' => $r->id,
                'reference' => $r->reference(),
                'total' => $r->total,
                'notes' => $r->notes,
                'created_by_name' => $r->created_by_name,
                'created_at' => $r->created_at?->toIso8601String(),
            ])->all() : [],
        ];
    }
}
