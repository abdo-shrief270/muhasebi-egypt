<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Enums\QualityGrade;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * What is actually sold and stocked: one colour / capacity / quality of a product, with its own
 * barcode and prices (piasters).
 *
 * @property string $id
 * @property string $tenant_id
 * @property string $product_id
 * @property string|null $name
 * @property QualityGrade|null $quality_grade
 * @property string|null $barcode
 * @property int $price_retail
 * @property int|null $price_wholesale
 * @property int|null $price_technician
 * @property int|null $price_online
 * @property int $min_stock
 * @property bool $is_active
 * @property int $sort
 * @property-read Product $product
 */
#[Fillable([
    'tenant_id', 'product_id', 'name', 'quality_grade', 'barcode', 'price_retail', 'price_wholesale',
    'price_technician', 'price_online', 'min_stock', 'is_active', 'sort',
])]
final class ProductVariant extends Model
{
    use BelongsToTenant, HasUuids;

    public const PRICE_FIELDS = ['price_retail', 'price_wholesale', 'price_technician', 'price_online'];

    protected $attributes = [
        'min_stock' => 0,
        'is_active' => true,
    ];

    protected function casts(): array
    {
        return [
            'quality_grade' => QualityGrade::class,
            'price_retail' => 'integer',
            'price_wholesale' => 'integer',
            'price_technician' => 'integer',
            'price_online' => 'integer',
            'min_stock' => 'integer',
            'is_active' => 'boolean',
            'sort' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /** "جراب سيليكون — أسود" */
    public function displayName(): string
    {
        return $this->name ? "{$this->product->name} — {$this->name}" : $this->product->name;
    }
}
