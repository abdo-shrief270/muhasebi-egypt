<?php

declare(strict_types=1);

namespace App\Modules\Catalog;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\SearchText;
use Illuminate\Database\Eloquent\Builder;

final class VariantCatalogService implements VariantCatalog
{
    public function find(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        return ProductVariant::query()
            ->with('product.category')
            ->whereIn('id', array_values(array_unique($variantIds)))
            ->get()
            ->mapWithKeys(fn (ProductVariant $v): array => [$v->id => $this->summary($v)])
            ->all();
    }

    public function search(?string $q, ?int $categoryId, ?array $onlyIds, int $page, int $perPage, bool $activeOnly = true, array $exceptIds = []): array
    {
        $query = ProductVariant::query()
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->select('product_variants.*')
            ->with('product.category')
            ->when($q !== null, function (Builder $b) use ($q): void {
                // Per variant: each word matches the product, or this variant's own name / barcode.
                foreach (SearchText::tokens((string) $q) as $token) {
                    $like = SearchText::like($token);
                    $b->where(fn (Builder $w) => $w
                        ->whereHas('product', fn (Builder $p) => $p->matchesProductFields($like))
                        ->orWhereRaw('lower(product_variants.barcode) like ?', [$like])
                        ->orWhereRaw('lower(product_variants.name) like ?', [$like]));
                }
            })
            ->when($categoryId !== null, fn (Builder $b) => $b->where('products.category_id', $categoryId))
            ->when($onlyIds !== null, fn (Builder $b) => $b->whereIn('product_variants.id', $onlyIds))
            ->when($exceptIds !== [], fn (Builder $b) => $b->whereNotIn('product_variants.id', $exceptIds))
            ->when($activeOnly, fn (Builder $b) => $b->where('products.is_active', true)->where('product_variants.is_active', true))
            ->orderBy('products.name')
            ->orderBy('product_variants.sort');

        $paginator = $query->paginate($perPage, page: $page);

        return [
            'items' => array_values(array_map($this->summary(...), $paginator->items())),
            'total' => $paginator->total(),
            'page' => $paginator->currentPage(),
            'per_page' => $paginator->perPage(),
            'last_page' => $paginator->lastPage(),
        ];
    }

    public function minStockThresholds(): array
    {
        return ProductVariant::query()->where('min_stock', '>', 0)->pluck('min_stock', 'id')->map(fn ($v) => (int) $v)->all();
    }

    private function summary(ProductVariant $v): VariantSummary
    {
        return new VariantSummary(
            id: $v->id,
            productId: $v->product_id,
            productName: $v->product->name,
            variantName: $v->name,
            barcode: $v->barcode,
            sku: $v->product->sku,
            categoryId: $v->product->category_id,
            categoryName: $v->product->category->name,
            priceRetail: $v->price_retail,
            minStock: $v->min_stock,
            isActive: $v->is_active && $v->product->is_active,
            trackSerial: $v->product->track_serial,
            priceWholesale: $v->price_wholesale,
            priceTechnician: $v->price_technician,
            qualityLabel: $v->quality_grade?->label(),
        );
    }
}
