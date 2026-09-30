<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

/**
 * Read access to the current shop's variants for other modules (stock, sales, purchases…).
 */
interface VariantCatalog
{
    /**
     * @param  list<string>  $variantIds
     * @return array<string, VariantSummary> keyed by id; unknown ids are left out
     */
    public function find(array $variantIds): array;

    /**
     * Variants ordered by product name. $q works like the products search (name, SKU, barcode,
     * brand, compatible models, Arabic spelling variants); $onlyIds narrows to those variants
     * and $exceptIds leaves those out.
     *
     * @param  list<string>|null  $onlyIds
     * @param  list<string>  $exceptIds
     * @return array{items: list<VariantSummary>, total: int, page: int, per_page: int, last_page: int}
     */
    public function search(?string $q, ?int $categoryId, ?array $onlyIds, int $page, int $perPage, bool $activeOnly = true, array $exceptIds = []): array;

    /**
     * Every selling price the variants had at the price level since $since (from the price
     * history, plus today's price): what an offline POS may have shown from its cached catalog.
     *
     * @param  list<string>  $variantIds
     * @return array<string, list<int>> variant id => prices
     */
    public function pricesSince(array $variantIds, string $level, \DateTimeInterface $since): array;

    /**
     * @return array<string, int> variant id => low-stock threshold, for variants that have one
     */
    public function minStockThresholds(): array;
}
