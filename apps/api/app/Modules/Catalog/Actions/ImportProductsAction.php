<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Enums\CategoryType;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\Import\ImportPlan;
use App\Modules\Catalog\Support\Import\ImportPlanner;
use App\Modules\Catalog\Support\Import\SheetReader;
use App\Modules\Catalog\Support\SearchText;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Products from an Excel / CSV sheet. preview() only reports; handle() writes the valid rows
 * (all of them, or none while any row is invalid unless $skipInvalid), and records the
 * quantity column as opening stock in the branch the import runs in.
 */
final class ImportProductsAction
{
    public function __construct(
        private readonly SheetReader $reader,
        private readonly Auditor $audit,
        private readonly StockLedger $stock,
    ) {}

    public function preview(string $branchId, UploadedFile $file, bool $canSetStock, bool $canSetCost): ImportPlan
    {
        return $this->planner($branchId, $canSetStock, $canSetCost)
            ->plan($this->reader->read((string) $file->getRealPath(), $this->extension($file)));
    }

    public function handle(string $tenantId, string $branchId, UploadedFile $file, bool $skipInvalid, bool $canSetStock, bool $canSetCost): ImportPlan
    {
        $rows = $this->reader->read((string) $file->getRealPath(), $this->extension($file));

        return DB::transaction(function () use ($tenantId, $branchId, $rows, $skipInvalid, $file, $canSetStock, $canSetCost): ImportPlan {
            // Planned inside the transaction so it sees exactly what it writes over.
            $plan = $this->planner($branchId, $canSetStock, $canSetCost)->plan($rows);

            if ($plan->hasErrors() && ! $skipInvalid) {
                throw new DomainRuleException('فيه صفوف فيها أخطاء. صلّحها أو اختار «استورد الصفوف السليمة بس».', 'import_has_errors');
            }
            if ($plan->okRows === []) {
                throw new DomainRuleException('مفيش ولا صف سليم يتستورد.', 'import_nothing_valid');
            }

            $categoryIds = $this->categoryIds($plan);
            $brandIds = $this->brandIds($plan);

            foreach ($plan->newProducts as ['product' => $p, 'variants' => $variants, 'models' => $models]) {
                $product = Product::create([
                    'tenant_id' => $tenantId,
                    'category_id' => $categoryIds[$p['category_key']],
                    'brand_id' => $p['brand_key'] !== null ? $brandIds[$p['brand_key']] : null,
                    'name' => $p['name'],
                    'sku' => $p['sku'],
                ]);
                foreach ($variants as $sort => ['data' => $data, 'stock' => $stock]) {
                    $variant = $product->variants()->create([...$this->forCreate($data), 'tenant_id' => $tenantId, 'sort' => $sort]);
                    $this->openingStock($branchId, $variant->id, $stock);
                }
                $product->deviceModels()->sync($models);
            }

            $nextSort = [];
            foreach ($plan->addedVariants as ['product_id' => $productId, 'data' => $data, 'models' => $models, 'stock' => $stock]) {
                $nextSort[$productId] ??= (int) ProductVariant::query()->where('product_id', $productId)->max('sort') + 1;
                $variant = ProductVariant::create([...$this->forCreate($data), 'tenant_id' => $tenantId, 'product_id' => $productId, 'sort' => $nextSort[$productId]++]);
                $this->openingStock($branchId, $variant->id, $stock);
                Product::query()->findOrFail($productId)->deviceModels()->syncWithoutDetaching($models);
            }

            foreach ($plan->updatedVariants as ['variant_id' => $variantId, 'product_id' => $productId, 'data' => $data, 'models' => $models, 'stock' => $stock]) {
                // Only what the sheet actually filled in; empty cells keep today's values.
                $changes = array_filter(
                    array_intersect_key($data, array_flip([...ProductVariant::PRICE_FIELDS, 'min_stock', 'quality_grade'])),
                    fn ($v) => $v !== null,
                );
                ProductVariant::query()->findOrFail($variantId)->update($changes);
                Product::query()->findOrFail($productId)->deviceModels()->syncWithoutDetaching($models);
                $this->openingStock($branchId, $variantId, $stock);
            }

            $summary = $plan->summary();
            $this->audit->record(
                'products.imported',
                "استورد أصناف من ملف «{$file->getClientOriginalName()}»: {$summary['new_products']} صنف جديد، {$summary['new_variants']} نوع جديد، و{$summary['updated_variants']} نوع اتحدّثت أسعاره"
                    .($summary['stock_units'] ? "، ورصيد افتتاحي {$summary['stock_units']} قطعة" : ''),
                properties: [
                    ...array_intersect_key($summary, array_flip(['rows', 'valid_rows', 'invalid_rows', 'new_products', 'new_variants', 'updated_variants', 'stock_units'])),
                    'branch_id' => $branchId,
                ],
                tenantId: $tenantId,
            );

            return $plan;
        });
    }

    private function planner(string $branchId, bool $canSetStock, bool $canSetCost): ImportPlanner
    {
        $withStock = $canSetStock ? $this->stock->variantsWithHistory(ProductVariant::query()->pluck('id')->all(), $branchId) : [];

        return new ImportPlanner($withStock, $canSetStock, $canSetCost);
    }

    /**
     * @param  array{qty: int, unit_cost: int}|null  $stock
     */
    private function openingStock(string $branchId, string $variantId, ?array $stock): void
    {
        if ($stock !== null) {
            $this->stock->receive($branchId, $variantId, $stock['qty'], $stock['unit_cost'], new StockReference(MovementType::Opening, refType: 'import'));
        }
    }

    /**
     * @return array<string, int> key => id, for existing and newly created categories
     */
    private function categoryIds(ImportPlan $plan): array
    {
        $sort = (int) Category::query()->max('sort');
        foreach ($plan->newCategories as $name) {
            Category::create(['name' => $name, 'type' => CategoryType::Other, 'sort' => ++$sort]);
        }

        return Category::query()->get(['id', 'name'])->mapWithKeys(fn (Category $c) => [SearchText::normalize($c->name) => $c->id])->all();
    }

    /**
     * @return array<string, int>
     */
    private function brandIds(ImportPlan $plan): array
    {
        $sort = (int) Brand::query()->max('sort');
        foreach ($plan->newBrands as $name) {
            Brand::create(['name' => $name, 'sort' => ++$sort]);
        }

        return Brand::query()->get(['id', 'name'])->mapWithKeys(fn (Brand $b) => [SearchText::normalize($b->name) => $b->id])->all();
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function forCreate(array $data): array
    {
        return [...$data, 'price_retail' => $data['price_retail'] ?? 0, 'min_stock' => $data['min_stock'] ?? 0];
    }

    private function extension(UploadedFile $file): string
    {
        return strtolower($file->getClientOriginalExtension()) === 'csv' ? 'csv' : 'xlsx';
    }
}
