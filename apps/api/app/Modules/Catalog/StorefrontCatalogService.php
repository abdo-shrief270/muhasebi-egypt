<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Contracts\StorefrontCatalog;
use App\Modules\Catalog\Contracts\StorefrontQuery;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\DeviceModel;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class StorefrontCatalogService implements StorefrontCatalog
{
    /** What the customer pays online: the online price, else retail. */
    private const PRICE = 'coalesce(product_variants.price_online, product_variants.price_retail)';

    public function categories(?array $onlyVariantIds = null): array
    {
        $counts = $this->shown($onlyVariantIds)->toBase()
            ->selectRaw('category_id, count(*) as n')->groupBy('category_id')->pluck('n', 'category_id');

        return Category::query()->whereIn('id', $counts->keys())->orderBy('sort')->orderBy('name')->get()
            ->map(fn (Category $c) => ['id' => $c->id, 'name' => $c->name, 'products' => (int) $counts[$c->id]])
            ->values()->all();
    }

    public function categoryNames(): array
    {
        return Category::query()->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
    }

    public function deviceBrands(?array $onlyVariantIds = null): array
    {
        $counts = DB::table('device_model_product')
            ->whereIn('product_id', $this->shown($onlyVariantIds)->select('products.id'))
            ->selectRaw('device_model_id, count(*) as n')->groupBy('device_model_id')->pluck('n', 'device_model_id');

        return DeviceModel::query()->with('brand')->whereIn('id', $counts->keys())->orderBy('name')->get()
            ->groupBy(fn (DeviceModel $m) => $m->brand_id ?? 0)
            ->map(fn ($models) => [
                'id' => $models->first()->brand_id,
                'name' => $models->first()->brand?->name ?? 'أخرى',
                'models' => $models->map(fn (DeviceModel $m) => ['id' => $m->id, 'name' => $m->name, 'products' => (int) $counts[$m->id]])->values()->all(),
            ])
            ->sortBy('name')->values()->all();
    }

    public function products(StorefrontQuery $query): array
    {
        $price = ProductVariant::query()->selectRaw('min('.self::PRICE.')')
            ->whereColumn('product_variants.product_id', 'products.id')->where('product_variants.is_active', true);

        $base = $this->shown($query->onlyVariantIds)
            ->when($query->q !== null && trim($query->q) !== '', fn (Builder $b) => $b->search((string) $query->q))
            ->when($query->categoryId !== null, fn (Builder $b) => $b->where('category_id', $query->categoryId))
            ->when($query->brandId !== null, fn (Builder $b) => $b->where('brand_id', $query->brandId))
            ->when($query->deviceModelId !== null, fn (Builder $b) => $b->whereHas('deviceModels', fn (Builder $m) => $m->whereKey($query->deviceModelId)))
            ->when($query->quality !== null, fn (Builder $b) => $b->whereHas('variants', fn (Builder $v) => $v->where('is_active', true)->where('quality_grade', $query->quality)))
            ->when($query->minPrice !== null, fn (Builder $b) => $b->where($price, '>=', $query->minPrice))
            ->when($query->maxPrice !== null, fn (Builder $b) => $b->where($price, '<=', $query->maxPrice));

        $total = (clone $base)->count();
        $rows = $base->select('products.*')->selectSub($price, 'shown_price')
            ->with(['brand', 'category', 'variants' => fn ($v) => $v->where('is_active', true), 'images'])
            ->when($query->sort === 'price_asc', fn (Builder $b) => $b->orderBy('shown_price'))
            ->when($query->sort === 'price_desc', fn (Builder $b) => $b->orderByDesc('shown_price'))
            ->when($query->sort === 'name', fn (Builder $b) => $b->orderBy('name'))
            ->orderByDesc('products.created_at')->orderBy('products.id')
            ->forPage(max(1, $query->page), min(60, max(1, $query->perPage)))
            ->get();

        return ['items' => $rows->map(fn (Product $p) => $this->card($p))->all(), 'total' => $total];
    }

    public function product(string $id): ?array
    {
        $product = $this->shown(null)->whereKey($id)
            ->with(['brand', 'category', 'images', 'deviceModels.brand', 'variants' => fn ($v) => $v->where('is_active', true)])
            ->first();
        if ($product === null) {
            return null;
        }

        return [
            ...$this->card($product),
            'description' => $product->online_description,
            'images' => $product->images->map(fn (ProductImage $i) => $i->toPublic())->all(),
            'variants' => $product->variants->map(fn (ProductVariant $v) => [
                'id' => $v->id,
                'name' => $v->name,
                'quality' => $v->quality_grade?->value,
                'quality_label' => $v->quality_grade?->label(),
                'price' => $v->price_online ?? $v->price_retail,
            ])->values()->all(),
            'device_models' => $product->deviceModels->map(fn (DeviceModel $m) => ['id' => $m->id, 'full_name' => trim(($m->brand?->name ?? '').' '.$m->name)])->values()->all(),
            'updated_at' => $product->updated_at?->toIso8601String(),
        ];
    }

    public function variants(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        return ProductVariant::query()
            ->whereIn('product_variants.id', $variantIds)
            ->where('is_active', true)
            ->whereIn('product_id', $this->shown(null)->select('products.id'))
            ->with('product')
            ->get()
            ->mapWithKeys(fn (ProductVariant $v) => [$v->id => [
                'id' => $v->id,
                'product_id' => $v->product_id,
                'name' => $v->name ? "{$v->product->name} — {$v->name}" : $v->product->name,
                'price' => (int) ($v->price_online ?? $v->price_retail),
            ]])
            ->all();
    }

    public function feed(int $limit): array
    {
        $rows = [];
        $this->shown(null)
            ->with(['brand', 'category', 'images', 'variants' => fn ($v) => $v->where('is_active', true)])
            ->orderBy('products.created_at')->orderBy('products.id')
            ->chunk(200, function ($products) use (&$rows, $limit): bool {
                foreach ($products as $p) {
                    /** @var Product $p */
                    $images = $p->images->map(fn (ProductImage $i) => $i->toPublic()['urls'])->values()->all();
                    foreach ($p->variants as $v) {
                        $rows[] = [
                            'id' => $v->id,
                            'product_id' => $p->id,
                            'title' => $v->name ? "{$p->name} — {$v->name}" : $p->name,
                            'description' => $p->online_description,
                            'brand' => $p->brand?->name,
                            'category' => ['id' => $p->category->id, 'name' => $p->category->name],
                            'price' => (int) ($v->price_online ?? $v->price_retail),
                            'image' => $images[0] ?? null,
                            'images' => array_slice($images, 1, 9),
                            'quality' => $v->quality_grade?->value,
                            'updated_at' => $p->updated_at?->toIso8601String(),
                        ];
                        if (count($rows) >= $limit) {
                            return false;
                        }
                    }
                }

                return true;
            });

        return $rows;
    }

    public function index(): array
    {
        return $this->shown(null)->orderBy('name')->get(['products.id', 'products.updated_at'])
            ->map(fn (Product $p) => ['id' => $p->id, 'updated_at' => (string) $p->updated_at?->toIso8601String()])->all();
    }

    /**
     * Active products the owner left on the store, that have an active variant (one of $onlyVariantIds when given).
     *
     * @param  list<string>|null  $onlyVariantIds
     * @return Builder<Product>
     */
    private function shown(?array $onlyVariantIds): Builder
    {
        return Product::query()
            ->where('products.is_active', true)
            ->where('products.online_visible', true)
            ->whereHas('variants', function (Builder $v) use ($onlyVariantIds): void {
                $v->where('is_active', true);
                if ($onlyVariantIds !== null) {
                    $v->whereIn('product_variants.id', $onlyVariantIds);
                }
            });
    }

    /**
     * @return array<string, mixed>
     */
    private function card(Product $p): array
    {
        $prices = $p->variants->map(fn (ProductVariant $v) => $v->price_online ?? $v->price_retail);
        $cover = $p->images->first();

        return [
            'id' => $p->id,
            'name' => $p->name,
            'brand' => $p->brand?->name,
            'category' => ['id' => $p->category->id, 'name' => $p->category->name],
            'image' => $cover?->toPublic(),
            'price' => (int) $prices->min(),
            'price_max' => (int) $prices->max(),
            'qualities' => $p->variants->map(fn (ProductVariant $v) => $v->quality_grade?->value)->filter()->unique()->values()->all(),
            'variant_ids' => $p->variants->pluck('id')->all(),
        ];
    }
}
