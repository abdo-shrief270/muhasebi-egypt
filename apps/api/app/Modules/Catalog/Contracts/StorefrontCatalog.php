<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

/**
 * The shop's catalog as its online store shows it: only active products the owner left visible
 * online, with their active variants, the online price (else retail) and photos. Never costs,
 * barcodes or suppliers. Runs in the current shop's context.
 */
interface StorefrontCatalog
{
    /**
     * Categories that have something to show.
     *
     * @param  list<string>|null  $onlyVariantIds  as StorefrontQuery
     * @return list<array{id: int, name: string, products: int}>
     */
    public function categories(?array $onlyVariantIds = null): array;

    /**
     * Every category of the shop by id (the store may show some under another name).
     *
     * @return array<int, string>
     */
    public function categoryNames(): array;

    /**
     * Phone brands → models that have compatible products to show («اختار موبايلك»).
     *
     * @param  list<string>|null  $onlyVariantIds
     * @return list<array{id: int|null, name: string, models: list<array{id: int, name: string, products: int}>}>
     */
    public function deviceBrands(?array $onlyVariantIds = null): array;

    /**
     * @return array{items: list<array<string, mixed>>, total: int}
     *                                                              items: id, name, brand, category {id, name}, image (urls|null), price, price_max, qualities, variant_ids
     */
    public function products(StorefrontQuery $query): array;

    /**
     * One product to show, or null when it isn't (any more) on the store.
     *
     * @return array<string, mixed>|null id, name, description, brand, category, images, variants [{id, name, quality, quality_label, price}], device_models [{id, full_name}], updated_at
     */
    public function product(string $id): ?array;

    /**
     * Variants a customer may order (active, of a product shown on the store) with what they cost
     * online — for pricing an order on the server, never from the cart.
     *
     * @param  list<string>  $variantIds
     * @return array<string, array{id: string, product_id: string, name: string, price: int}> keyed by id; others left out
     */
    public function variants(array $variantIds): array;

    /**
     * Everything on the store, for its sitemap / catalog feed.
     *
     * @return list<array{id: string, updated_at: string}>
     */
    public function index(): array;
}
