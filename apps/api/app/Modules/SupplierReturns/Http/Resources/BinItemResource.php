<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Http\Resources;

use App\Modules\SupplierReturns\Models\BinItem;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property BinItem $resource
 */
final class BinItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $item = $this->resource;
        $cost = (bool) $request->user()?->can('products.view_cost');

        return [
            'id' => $item->id,
            'variant_id' => $item->variant_id,
            'name' => $item->variant_name,
            'qty' => $item->qty,
            'serial' => $item->serial,
            'unit_cost' => $cost ? $item->unit_cost : null,
            'value' => $cost ? $item->value() : null,
            'source' => $item->source_type === null ? null : [
                'type' => $item->source_type->value,
                'type_label' => $item->source_type->label(),
                'id' => $item->source_id,
                'name' => $item->source_name,
            ],
            'source_doc' => $item->source_doc,
            'detected_by' => $item->detected_by,
            'reason' => $item->reason->value,
            'reason_label' => $item->reason->label(),
            'note' => $item->note,
            'origin' => $item->origin,
            'origin_label' => $item->origin_label ?? 'من المخزون',
            'status' => $item->status,
            'accepted_qty' => $item->accepted_qty,
            'outcome' => $item->outcome,
            'return_id' => $item->supplier_return_id,
            'created_by_name' => $item->created_by_name,
            'created_at' => $item->created_at->toIso8601String(),
        ];
    }
}
