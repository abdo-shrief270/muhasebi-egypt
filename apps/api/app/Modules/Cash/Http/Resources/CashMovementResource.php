<?php

declare(strict_types=1);

namespace App\Modules\Cash\Http\Resources;

use App\Modules\Cash\Models\CashMovement;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashMovement
 */
final class CashMovementResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'method' => $this->method->value,
            'method_label' => $this->method->label(),
            'amount' => $this->amount,
            'category' => $this->category?->value,
            'category_label' => $this->category?->label(),
            'ref_type' => $this->ref_type,
            'ref_id' => $this->ref_id,
            'note' => $this->note,
            'user_name' => $this->user_name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
