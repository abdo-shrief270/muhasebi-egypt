<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Prices are piasters.
 *
 * @mixin ProductVariant
 */
final class ProductVariantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'quality_grade' => $this->quality_grade?->value,
            'quality_label' => $this->quality_grade?->label(),
            'barcode' => $this->barcode,
            'price_retail' => $this->price_retail,
            'price_wholesale' => $this->price_wholesale,
            'price_technician' => $this->price_technician,
            'price_online' => $this->price_online,
            'min_stock' => $this->min_stock,
            'is_active' => $this->is_active,
        ];
    }
}
