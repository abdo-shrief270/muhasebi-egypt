<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Resources;

use App\Modules\Suppliers\Models\Supplier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Supplier
 */
final class SupplierResource extends JsonResource
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
            'is_active' => $this->is_active,
            'purchases_count' => $this->whenCounted('purchases'),
            'last_purchase_at' => $this->whenHas('last_purchase_at', fn () => $this->getAttribute('last_purchase_at')),
        ];
    }
}
