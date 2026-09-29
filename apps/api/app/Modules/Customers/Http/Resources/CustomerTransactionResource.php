<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Models\CustomerTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CustomerTransaction
 */
final class CustomerTransactionResource extends JsonResource
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
            'amount' => $this->amount,
            'balance_after' => $this->balance_after,
            'payment_method' => $this->payment_method?->value,
            'payment_method_label' => $this->payment_method?->label(),
            'ref_type' => $this->ref_type,
            'ref_id' => $this->ref_id,
            'reference' => $this->reference,
            'note' => $this->note,
            'user_name' => $this->user_name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
