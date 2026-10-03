<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

use App\Modules\Catalog\Contracts\StorefrontCatalog;
use App\Modules\Catalog\Contracts\StorefrontQuery;
use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\OnlineStore\Models\OnlineStore;

/**
 * The public store's pages: the catalog (Catalog contract) with each variant's availability in
 * the store's branch (Inventory contract) — «متوفر / قرّب يخلص / خلص», the count only when the
 * owner shows it.
 */
final class Storefront
{
    public const LOW = 2;

    public function __construct(
        private readonly StorefrontCatalog $catalog,
        private readonly StockLedger $stock,
        private readonly BranchDirectory $branches,
    ) {}

    /** @return array<string, mixed> */
    public function home(OnlineStore $store): array
    {
        $only = $this->onlyInStock($store);

        return [
            'store' => $store->toPublic(),
            'categories' => $this->catalog->categories($only),
            'device_brands' => $this->catalog->deviceBrands($only),
            'latest' => $this->withAvailability($store, $this->catalog->products(new StorefrontQuery(perPage: 12, onlyVariantIds: $only))['items']),
        ];
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function products(OnlineStore $store, StorefrontQuery $query): array
    {
        $query = new StorefrontQuery(
            $query->q, $query->categoryId, $query->brandId, $query->deviceModelId, $query->quality,
            $query->minPrice, $query->maxPrice, $query->sort, $query->page, $query->perPage, $this->onlyInStock($store),
        );
        $page = $this->catalog->products($query);

        return ['items' => $this->withAvailability($store, $page['items']), 'total' => $page['total']];
    }

    /** @return array<string, mixed>|null */
    public function product(OnlineStore $store, string $id): ?array
    {
        $product = $this->catalog->product($id);
        if ($product === null) {
            return null;
        }
        $qty = $this->stock->quantities($this->branch($store), array_column($product['variants'], 'id'));
        if (! $store->show_out_of_stock && max([0, ...array_values($qty)]) <= 0) {
            return null;
        }
        $product['variants'] = array_map(fn (array $v) => [...$v, ...$this->availability($store, $qty[$v['id']] ?? 0)], $product['variants']);

        return [...$product, ...$this->availability($store, max([0, ...array_values($qty)]))];
    }

    /** @return list<array{id: string, updated_at: string}> */
    public function index(OnlineStore $store): array
    {
        return $this->catalog->index();
    }

    /**
     * @param  list<array<string, mixed>>  $items
     * @return list<array<string, mixed>>
     */
    private function withAvailability(OnlineStore $store, array $items): array
    {
        $qty = $this->stock->quantities($this->branch($store), array_merge(...array_map(fn (array $i) => $i['variant_ids'], $items) ?: [[]]));

        return array_map(function (array $item) use ($store, $qty): array {
            $best = max([0, ...array_map(fn (string $id) => $qty[$id] ?? 0, $item['variant_ids'])]);
            unset($item['variant_ids']);

            return [...$item, ...$this->availability($store, $best)];
        }, $items);
    }

    /** @return array{availability: string, quantity?: int} */
    private function availability(OnlineStore $store, int $qty): array
    {
        $state = $qty <= 0 ? 'out' : ($qty <= self::LOW ? 'low' : 'in');

        return $store->show_quantity ? ['availability' => $state, 'quantity' => max(0, $qty)] : ['availability' => $state];
    }

    /** @return list<string>|null */
    private function onlyInStock(OnlineStore $store): ?array
    {
        return $store->show_out_of_stock ? null : $this->stock->inStock($this->branch($store));
    }

    private function branch(OnlineStore $store): string
    {
        return (string) ($store->branch_id ?? $this->branches->mainBranchId());
    }
}
