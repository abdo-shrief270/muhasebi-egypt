<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Repairs\Contracts\TechnicianPricing;
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
        // A technician who only asks for parts sees the technician price, nothing else.
        $technicianOnly = app(TechnicianPricing::class)->technicianOnly($request->user());

        return [
            'id' => $this->id,
            'name' => $this->name,
            'quality_grade' => $this->quality_grade?->value,
            'quality_label' => $this->quality_grade?->label(),
            'barcode' => $this->barcode,
            'price_retail' => $technicianOnly ? null : $this->price_retail,
            'price_wholesale' => $technicianOnly ? null : $this->price_wholesale,
            'price_technician' => $technicianOnly ? ($this->price_technician ?? $this->price_retail) : $this->price_technician,
            'price_online' => $technicianOnly ? null : $this->price_online,
            'min_stock' => $this->min_stock,
            'is_active' => $this->is_active,
        ];
    }
}
