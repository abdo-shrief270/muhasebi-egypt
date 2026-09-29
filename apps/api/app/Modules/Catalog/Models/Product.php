<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Models;

use App\Modules\Catalog\Support\SearchText;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property string $id
 * @property string $tenant_id
 * @property int $category_id
 * @property int|null $brand_id
 * @property string $name
 * @property string $search_name
 * @property string|null $sku
 * @property bool $track_serial
 * @property bool $is_active
 * @property string|null $notes
 * @property-read Category $category
 * @property-read Brand|null $brand
 * @property-read Collection<int, ProductVariant> $variants
 * @property-read Collection<int, DeviceModel> $deviceModels
 */
#[Fillable(['tenant_id', 'category_id', 'brand_id', 'name', 'sku', 'track_serial', 'is_active', 'notes'])]
final class Product extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = [
        'track_serial' => false,
        'is_active' => true,
    ];

    protected static function booted(): void
    {
        self::saving(function (Product $product): void {
            $product->search_name = SearchText::normalize($product->name);
        });
    }

    protected function casts(): array
    {
        return [
            'track_serial' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<Brand, $this>
     */
    public function brand(): BelongsTo
    {
        return $this->belongsTo(Brand::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class)->orderBy('sort');
    }

    /**
     * @return BelongsToMany<DeviceModel, $this>
     */
    public function deviceModels(): BelongsToMany
    {
        return $this->belongsToMany(DeviceModel::class);
    }

    /**
     * Every word of the query must match the product's name, SKU, a variant's name or barcode,
     * its brand, or a phone model it fits — so "جراب iPhone 13" finds iPhone 13 cases.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeSearch(Builder $query, string $text): void
    {
        foreach (SearchText::tokens($text) as $token) {
            $like = SearchText::like($token);

            $query->where(function (Builder $q) use ($like): void {
                $q->matchesProductFields($like)
                    ->orWhereHas('variants', fn (Builder $v) => $v->whereRaw('lower(barcode) like ?', [$like])->orWhereRaw('lower(name) like ?', [$like]));
            });
        }
    }

    /**
     * One word against what belongs to the product itself (not its variants). Used by variant
     * searches, where a variant's own name and barcode are matched separately.
     *
     * @param  Builder<Product>  $query
     */
    public function scopeMatchesProductFields(Builder $query, string $like): void
    {
        $query->where(fn (Builder $q) => $q
            ->where('products.search_name', 'like', $like)
            ->orWhereRaw('lower(products.sku) like ?', [$like])
            ->orWhereHas('brand', fn (Builder $b) => $b->whereRaw('lower(name) like ?', [$like]))
            ->orWhereHas('deviceModels', fn (Builder $m) => $m->where('device_models.search_name', 'like', $like)));
    }
}
