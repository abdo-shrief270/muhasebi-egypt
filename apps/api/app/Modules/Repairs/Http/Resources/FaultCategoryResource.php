<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Resources;

use App\Modules\Repairs\Models\FaultCategory;
use App\Modules\Repairs\Models\FaultType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin FaultCategory
 */
final class FaultCategoryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'types' => $this->whenLoaded('types', fn () => $this->types->map(fn (FaultType $type): array => [
                'id' => $type->id,
                'name' => $type->name,
                'default_labor_price' => $type->default_labor_price,
                'is_active' => $type->is_active,
            ])),
        ];
    }
}
