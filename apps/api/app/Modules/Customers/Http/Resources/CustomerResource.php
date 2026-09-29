<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Resources;

use App\Modules\Customers\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Customer
 */
final class CustomerResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'notes' => $this->notes,
            'balance' => $this->balance,
            'credit_limit' => $this->credit_limit,
            'is_active' => $this->is_active,
            'last_activity_at' => $this->last_activity_at?->toIso8601String(),
            'created_at' => $this->created_at?->toIso8601String(),
            'data_consent' => $this->data_consent,
            'data_consent_at' => $this->data_consent_at?->toIso8601String(),
            'data_consent_by_name' => $this->data_consent_by_name,
            'erased_at' => $this->erased_at?->toIso8601String(),
        ];
    }
}
