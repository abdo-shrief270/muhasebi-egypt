<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

/** What an online-store page lists: filters, order and page. */
final readonly class StorefrontQuery
{
    public const SORTS = ['new', 'price_asc', 'price_desc', 'name'];

    /**
     * @param  list<string>|null  $onlyVariantIds  only products with one of these variants (e.g. the in-stock ones)
     */
    public function __construct(
        public ?string $q = null,
        public ?int $categoryId = null,
        public ?int $brandId = null,
        public ?int $deviceModelId = null,
        public ?string $quality = null,
        public ?int $minPrice = null,
        public ?int $maxPrice = null,
        public string $sort = 'new',
        public int $page = 1,
        public int $perPage = 24,
        public ?array $onlyVariantIds = null,
    ) {}
}
