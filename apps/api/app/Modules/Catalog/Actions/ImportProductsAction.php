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
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Products from an Excel / CSV sheet. preview() only reports; handle() writes the valid rows
 * (all of them, or none while any row is invalid unless $skipInvalid).
 */
final class ImportProductsAction
{
    public function __construct(
        private readonly SheetReader $reader,
        private readonly Auditor $audit,
    ) {}

    public function preview(UploadedFile $file): ImportPlan
    {
        return (new ImportPlanner)->plan($this->reader->read((string) $file->getRealPath(), $this->extension($file)));
    }

    public function handle(string $tenantId, UploadedFile $file, bool $skipInvalid): ImportPlan
    {
        $rows = $this->reader->read((string) $file->getRealPath(), $this->extension($file));

        return DB::transaction(function () use ($tenantId, $rows, $skipInvalid, $file): ImportPlan {
            // Planned inside the transaction so it sees exactly what it writes over.
            $plan = (new ImportPlanner)->plan($rows);

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
                foreach ($variants as $sort => ['data' => $data]) {
                    $product->variants()->create([...$this->forCreate($data), 'tenant_id' => $tenantId, 'sort' => $sort]);
                }
                $product->deviceModels()->sync($models);
            }

            $nextSort = [];
            foreach ($plan->addedVariants as ['product_id' => $productId, 'data' => $data, 'models' => $models]) {
                $nextSort[$productId] ??= (int) ProductVariant::query()->where('product_id', $productId)->max('sort') + 1;
                ProductVariant::create([...$this->forCreate($data), 'tenant_id' => $tenantId, 'product_id' => $productId, 'sort' => $nextSort[$productId]++]);
                Product::query()->findOrFail($productId)->deviceModels()->syncWithoutDetaching($models);
            }

            foreach ($plan->updatedVariants as ['variant_id' => $variantId, 'product_id' => $productId, 'data' => $data, 'models' => $models]) {
                // Only what the sheet actually filled in; empty cells keep today's values.
                $changes = array_filter(
                    array_intersect_key($data, array_flip([...ProductVariant::PRICE_FIELDS, 'min_stock', 'quality_grade'])),
                    fn ($v) => $v !== null,
                );
                ProductVariant::query()->findOrFail($variantId)->update($changes);
                Product::query()->findOrFail($productId)->deviceModels()->syncWithoutDetaching($models);
            }

            $summary = $plan->summary();
            $this->audit->record(
                'products.imported',
                "استورد أصناف من ملف «{$file->getClientOriginalName()}»: {$summary['new_products']} صنف جديد، {$summary['new_variants']} نوع جديد، و{$summary['updated_variants']} نوع اتحدّثت أسعاره",
                properties: array_intersect_key($summary, array_flip(['rows', 'valid_rows', 'invalid_rows', 'new_products', 'new_variants', 'updated_variants'])),
                tenantId: $tenantId,
            );

            return $plan;
        });
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
