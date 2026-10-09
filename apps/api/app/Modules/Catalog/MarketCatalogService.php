<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Contracts\MarketCatalog;
use App\Modules\Catalog\Models\DeviceModel;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductImage;
use App\Modules\Catalog\Models\ProductVariant;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class MarketCatalogService implements MarketCatalog
{
    public function offers(?array $variantIds = null): array
    {
        if ($variantIds === []) {
            return [];
        }
        $rows = [];
        ProductVariant::query()
            ->where('product_variants.is_active', true)
            ->whereRaw('coalesce(product_variants.price_online, product_variants.price_retail) > 0')
            ->when($variantIds !== null, fn (Builder $q) => $q->whereIn('product_variants.id', $variantIds))
            ->whereHas('product', fn (Builder $p) => $p->where('is_active', true))
            ->with(['product.brand', 'product.category', 'product.images', 'product.deviceModels.brand'])
            ->orderBy('product_variants.id')
            ->chunk(500, function ($variants) use (&$rows): void {
                foreach ($variants as $v) {
                    /** @var ProductVariant $v */
                    $p = $v->product;
                    $cover = $p->images->first();
                    $rows[] = [
                        'variant_id' => $v->id,
                        'product_id' => $p->id,
                        'title' => $v->name ? "{$p->name} — {$v->name}" : $p->name,
                        'description' => $p->online_description,
                        'brand' => $p->brand?->name,
                        'category' => ['id' => $p->category->id, 'name' => $p->category->name, 'type' => $p->category->type->value],
                        'used' => $p->category->name === UsedDeviceCatalogService::CATEGORY,
                        'quality' => $v->quality_grade?->value,
                        'quality_label' => $v->quality_grade?->label(),
                        'models' => $p->deviceModels->map(fn (DeviceModel $m) => $m->fullName())->values()->all(),
                        'price' => (int) ($v->price_online ?? $v->price_retail),
                        'image' => $cover instanceof ProductImage ? $cover->toPublic()['urls'] : null,
                        'images' => $p->images->count(),
                        'updated_at' => max($p->updated_at, $v->updated_at)?->toIso8601String(),
                    ];
                }
            });

        return $rows;
    }

    public function changedSince(DateTimeInterface $since): array
    {
        $changedProducts = Product::withoutTenancy()->select('id')->where('updated_at', '>', $since)
            ->union(DB::table('product_images')->select('product_id')->where('updated_at', '>', $since));

        $out = [];
        DB::table('product_variants')
            ->where('updated_at', '>', $since)
            ->orWhereIn('product_id', $changedProducts)
            ->orderBy('tenant_id')
            ->select(['tenant_id', 'id'])
            ->each(function (object $row) use (&$out): void {
                $out[$row->tenant_id][] = $row->id;
            });

        return $out;
    }

    public function productNames(array $productIds): array
    {
        return $productIds === [] ? [] : Product::query()->whereIn('id', $productIds)->orderBy('name')
            ->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
    }
}
