<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Resources;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Product
 */
final class ProductResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'sku' => $this->sku,
            'track_serial' => $this->track_serial,
            'is_active' => $this->is_active,
            'notes' => $this->notes,
            'online_visible' => $this->online_visible,
            'online_description' => $this->online_description,
            'images' => $this->whenLoaded('images', fn () => $this->images->map(fn (ProductImage $i) => $i->toPublic())->all()),
            'category' => $this->whenLoaded('category', fn () => ['id' => $this->category->id, 'name' => $this->category->name]),
            'brand' => $this->whenLoaded('brand', fn () => $this->brand ? ['id' => $this->brand->id, 'name' => $this->brand->name] : null),
            'variants' => ProductVariantResource::collection($this->whenLoaded('variants')),
            'device_models' => DeviceModelResource::collection($this->whenLoaded('deviceModels')),
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
