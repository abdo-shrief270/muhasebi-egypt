<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Support\ProductImages;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;

/**
 * A product photo, stored as WebP in ProductImages::SIZES widths and served publicly
 * (GET /public/media/products/{tenant}/{id}-{width}.webp).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $product_id
 * @property int $sort
 * @property int $width
 * @property int $height
 */
#[Fillable(['tenant_id', 'product_id', 'sort', 'width', 'height'])]
final class ProductImage extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return ['sort' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    /**
     * @return array{id: string, width: int, height: int, urls: array<int, string>}
     */
    public function toPublic(): array
    {
        return [
            'id' => $this->id,
            'width' => $this->width,
            'height' => $this->height,
            'urls' => ProductImages::urls($this->tenant_id, $this->id),
        ];
    }
}
