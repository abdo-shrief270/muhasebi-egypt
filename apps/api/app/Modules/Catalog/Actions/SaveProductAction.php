<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\PriceHistory;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Creates or updates a product together with its variants and compatible phone models.
 * Variants sent with an id are updated, without one are created, and the ones left out are removed.
 */
final class SaveProductAction
{
    public function __construct(
        private readonly Auditor $audit,
        private readonly StockLedger $stock,
        private readonly PriceHistory $history,
    ) {}

    /**
     * @param  array{category_id?: int, brand_id?: int|null, name?: string, sku?: string|null, track_serial?: bool, is_active?: bool, notes?: string|null}  $data
     * @param  list<array<string, mixed>>|null  $variants  null = leave variants as they are
     * @param  list<int>|null  $deviceModelIds  null = leave compatibility as it is
     */
    public function handle(string $tenantId, array $data, ?array $variants, ?array $deviceModelIds, ?Product $product = null): Product
    {
        $creating = $product === null;

        return DB::transaction(function () use ($tenantId, $data, $variants, $deviceModelIds, $product, $creating): Product {
            $product ??= new Product(['tenant_id' => $tenantId]);
            $product->fill($data)->save();

            $priceChanges = $variants === null ? [] : $this->syncVariants($product, $variants);

            if ($deviceModelIds !== null) {
                $product->deviceModels()->sync($deviceModelIds);
            }

            $this->audit->record(
                $creating ? 'products.created' : 'products.updated',
                ($creating ? 'أضاف' : 'عدّل')." الصنف «{$product->name}»".($priceChanges ? ' وغيّر أسعاره' : ''),
                $product,
                $priceChanges ? ['price_changes' => $priceChanges] : [],
            );

            return $product->load(['category', 'brand', 'variants', 'deviceModels.brand', 'images']);
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array{variant: string, field: string, from: int|null, to: int|null}>
     */
    private function syncVariants(Product $product, array $rows): array
    {
        $existing = $product->variants()->get()->keyBy('id');
        $kept = array_values(array_filter(array_column($rows, 'id')));

        if (array_diff($kept, $existing->keys()->all()) !== []) {
            throw new DomainRuleException('فيه متغير مش تبع الصنف ده.', 'variant_not_found', 404);
        }

        // A variant with stock history can't disappear (its movements point at it): deactivate it instead.
        $removedIds = array_values(array_diff($existing->keys()->all(), $kept));
        if ($used = $this->stock->variantsWithHistory($removedIds)) {
            $names = collect($used)->map(fn (string $id) => $existing->get($id)?->name ?? $product->name)->implode('، ');
            throw new DomainRuleException(
                "مينفعش تمسح «{$names}» لأن عليه حركات مخزون. وقّفه بدل ما تمسحه.",
                'variant_has_stock_history',
            );
        }

        // Removed first, so a barcode can move from a removed variant to a new one.
        $product->variants()->whereNotIn('id', $kept)->delete();
        $priceChanges = [];

        foreach (array_values($rows) as $sort => $row) {
            $id = $row['id'] ?? null;

            /** @var ProductVariant $variant */
            $variant = $id !== null ? $existing->get($id) : new ProductVariant(['tenant_id' => $product->tenant_id]);
            $variant->fill([...array_diff_key($row, ['id' => true]), 'sort' => $sort]);
            $variant->product()->associate($product);

            $changed = [];
            if ($variant->exists) {
                foreach (ProductVariant::PRICE_FIELDS as $field) {
                    if ($variant->isDirty($field)) {
                        $priceChanges[] = [
                            'variant' => $variant->name ?? $product->name,
                            'field' => $field,
                            'from' => $variant->getOriginal($field),
                            'to' => $variant->getAttribute($field),
                        ];
                        $changed[$field] = $variant->getOriginal($field);
                    }
                }
            }

            $variant->save();

            foreach ($changed as $field => $from) {
                $this->history->record($variant, $field, $from, $variant->getAttribute($field), 'edit');
            }
        }

        return $priceChanges;
    }
}
