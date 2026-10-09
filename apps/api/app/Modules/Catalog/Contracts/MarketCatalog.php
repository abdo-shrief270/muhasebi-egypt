<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

use DateTimeInterface;

/**
 * What a shop offers in «سوق محاسبي»: every active variant of an active product with a selling
 * price (the online price, else retail), whatever the online store shows. Never costs, barcodes,
 * suppliers or wholesale prices. Runs in the current shop's context, except changedSince().
 */
interface MarketCatalog
{
    /**
     * @param  list<string>|null  $variantIds  only these (null = all); unknown or no-longer-offered ones are left out
     * @return list<array{variant_id: string, product_id: string, title: string, description: string|null, brand: string|null, category: array{id: int, name: string, type: string}, used: bool, quality: string|null, quality_label: string|null, models: list<string>, price: int, image: array<string, string>|null, images: int, updated_at: string|null}>
     */
    public function offers(?array $variantIds = null): array;

    /**
     * Variants whose product, variant or photos changed after $since, in every shop (compatibility
     * edits reach the market with the nightly rebuild).
     *
     * @return array<string, list<string>> tenant id => variant ids
     */
    public function changedSince(DateTimeInterface $since): array;

    /**
     * @param  list<string>  $productIds
     * @return array<string, string> id => name, for the current shop's products among them
     */
    public function productNames(array $productIds): array;
}
