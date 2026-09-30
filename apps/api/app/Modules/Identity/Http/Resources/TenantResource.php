<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Resources;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Support\ReceiptSettings;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Tenant
 */
final class TenantResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'code' => $this->code,
            'phone' => $this->phone,
            'shop_type' => $this->shop_type->value,
            'shop_types' => array_map(fn (ShopType $t) => $t->value, $this->types()),
            'shop_type_label' => ShopType::labels($this->types()),
            'receipt' => ReceiptSettings::of($this->resource),
        ];
    }
}
