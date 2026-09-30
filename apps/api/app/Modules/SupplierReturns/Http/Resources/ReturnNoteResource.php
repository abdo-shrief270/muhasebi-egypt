<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Http\Resources;

use App\Modules\SupplierReturns\Models\ReturnNote;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @property ReturnNote $resource
 */
final class ReturnNoteResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $note = $this->resource;
        $cost = (bool) $request->user()?->can('products.view_cost');

        return [
            'id' => $note->id,
            'reference' => $note->reference(),
            'source' => [
                'type' => $note->source_type->value,
                'type_label' => $note->source_type->label(),
                'id' => $note->source_id,
                'name' => $note->source_name,
                'phone' => $note->source_phone,
            ],
            'status' => $note->status->value,
            'status_label' => $note->status->label(),
            'units' => $note->units,
            'total_cost' => $cost ? $note->total_cost : null,
            'accepted_value' => $cost ? $note->accepted_value : null,
            'rejected_value' => $cost ? $note->rejected_value : null,
            'resolution' => $note->resolution,
            'refund_method' => $note->refund_method,
            'rejected_action' => $note->rejected_action,
            'notes' => $note->notes,
            'settle_note' => $note->settle_note,
            'created_by_name' => $note->created_by_name,
            'settled_by_name' => $note->settled_by_name,
            'created_at' => $note->created_at->toIso8601String(),
            'sent_at' => $note->sent_at?->toIso8601String(),
            'settled_at' => $note->settled_at?->toIso8601String(),
            'items' => $this->when($note->relationLoaded('items'), fn () => BinItemResource::collection($note->items)),
        ];
    }
}
