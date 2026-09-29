<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support\Import;

/**
 * What an import would do: per-row errors and warnings, and the writes for the valid rows.
 */
final class ImportPlan
{
    /** @var array<int, list<string>> */
    public array $errors = [];

    /** @var array<int, list<string>> */
    public array $warnings = [];

    /** @var list<int> */
    public array $okRows = [];

    /** @var array<string, string> key => name */
    public array $newCategories = [];

    /** @var array<string, string> key => name */
    public array $newBrands = [];

    /** @var array<string, array{product: array{name: string, category_key: string, brand_key: string|null, sku: string|null}, variants: list<array{line: int, data: array<string, mixed>, stock: array{qty: int, unit_cost: int}|null}>, models: list<int>}> */
    public array $newProducts = [];

    /** @var list<array{line: int, product_id: string, data: array<string, mixed>, models: list<int>, stock: array{qty: int, unit_cost: int}|null}> */
    public array $addedVariants = [];

    /** @var list<array{line: int, variant_id: string, product_id: string, data: array<string, mixed>, models: list<int>, stock: array{qty: int, unit_cost: int}|null}> */
    public array $updatedVariants = [];

    /**
     * @param  list<string>  $errors
     * @param  list<string>  $warnings
     */
    public function rowFailed(int $line, array $errors, array $warnings): void
    {
        $this->errors[$line] = $errors;
        if ($warnings !== []) {
            $this->warnings[$line] = $warnings;
        }
    }

    /**
     * @param  list<string>  $warnings
     */
    public function rowOk(int $line, array $warnings): void
    {
        $this->okRows[] = $line;
        if ($warnings !== []) {
            $this->warnings[$line] = $warnings;
        }
    }

    public function newCategory(string $key, string $name): void
    {
        $this->newCategories[$key] ??= $name;
    }

    public function newBrand(string $key, string $name): void
    {
        $this->newBrands[$key] ??= $name;
    }

    public function hasNewProduct(string $groupKey): bool
    {
        return isset($this->newProducts[$groupKey]);
    }

    /**
     * @param  array{name: string, category_key: string, brand_key: string|null, sku: string|null}  $product
     */
    public function newProduct(string $groupKey, array $product): void
    {
        $this->newProducts[$groupKey] = ['product' => $product, 'variants' => [], 'models' => []];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $models
     * @param  array{qty: int, unit_cost: int}|null  $stock
     */
    public function newProductVariant(int $line, string $groupKey, array $data, array $models, ?array $stock = null): void
    {
        $this->newProducts[$groupKey]['variants'][] = ['line' => $line, 'data' => $data, 'stock' => $stock];
        $this->newProducts[$groupKey]['models'] = array_values(array_unique([...$this->newProducts[$groupKey]['models'], ...$models]));
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $models
     * @param  array{qty: int, unit_cost: int}|null  $stock
     */
    public function addVariant(int $line, string $productId, array $data, array $models, ?array $stock = null): void
    {
        $this->addedVariants[] = ['line' => $line, 'product_id' => $productId, 'data' => $data, 'models' => $models, 'stock' => $stock];
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $models
     * @param  array{qty: int, unit_cost: int}|null  $stock
     */
    public function updateVariant(int $line, string $variantId, string $productId, array $data, array $models, ?array $stock = null): void
    {
        $this->updatedVariants[] = ['line' => $line, 'variant_id' => $variantId, 'product_id' => $productId, 'data' => $data, 'models' => $models, 'stock' => $stock];
    }

    /** Opening-stock units the import records. */
    public function stockUnits(): int
    {
        $units = 0;
        foreach ($this->newProducts as $product) {
            foreach ($product['variants'] as $variant) {
                $units += $variant['stock']['qty'] ?? 0;
            }
        }
        foreach ([...$this->addedVariants, ...$this->updatedVariants] as $row) {
            $units += $row['stock']['qty'] ?? 0;
        }

        return $units;
    }

    public function hasErrors(): bool
    {
        return $this->errors !== [];
    }

    /**
     * For the preview screen. Long lists are cut to keep the response small.
     *
     * @return array<string, mixed>
     */
    public function summary(): array
    {
        $rows = fn (array $byLine): array => array_map(
            fn (int $line, array $messages): array => ['row' => $line, 'messages' => $messages],
            array_keys(array_slice($byLine, 0, 200, true)),
            array_slice($byLine, 0, 200, true),
        );

        return [
            'rows' => count($this->okRows) + count($this->errors),
            'valid_rows' => count($this->okRows),
            'invalid_rows' => count($this->errors),
            'new_products' => count($this->newProducts),
            'new_variants' => array_sum(array_map(fn ($p) => count($p['variants']), $this->newProducts)) + count($this->addedVariants),
            'updated_variants' => count($this->updatedVariants),
            'stock_units' => $this->stockUnits(),
            'new_categories' => array_values($this->newCategories),
            'new_brands' => array_values($this->newBrands),
            'errors' => $rows($this->errors),
            'warnings' => $rows($this->warnings),
        ];
    }
}
