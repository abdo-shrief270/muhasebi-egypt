<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

use App\Modules\Catalog\Contracts\StorefrontCatalog;
use App\Modules\Catalog\Contracts\StorefrontQuery;
use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\OnlineStore\Models\DeliveryZone;
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
            'device_brands' => $store->show_models ? $this->catalog->deviceBrands($only) : [],
            'latest' => $store->show_latest
                ? $this->withAvailability($store, $this->catalog->products(new StorefrontQuery(perPage: 12, onlyVariantIds: $only))['items'])
                : [],
            'zones' => $store->takesOrders() && $store->delivery
                ? DeliveryZone::query()->where('is_active', true)->orderBy('sort')->orderBy('name')->get()
                    ->map(fn (DeliveryZone $z) => ['id' => $z->id, 'name' => $z->name, 'fee' => $z->fee])->all()
                : [],
        ];
    }

    /** @return array{items: list<array<string, mixed>>, total: int} */
    public function products(OnlineStore $store, StorefrontQuery $query): array
    {
        // Hidden prices can't be filtered or sorted by (that would give them away); hidden models aren't browsed.
        $prices = $store->show_prices;
        $query = new StorefrontQuery(
            $query->q, $query->categoryId, $query->brandId, $store->show_models ? $query->deviceModelId : null, $query->quality,
            $prices ? $query->minPrice : null, $prices ? $query->maxPrice : null,
            ! $prices && in_array($query->sort, ['price_asc', 'price_desc'], true) ? 'new' : $query->sort,
            $query->page, $query->perPage, $this->onlyInStock($store),
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
        $product['variants'] = array_map(fn (array $v) => $this->priced($store, [...$v, ...$this->availability($store, $qty[$v['id']] ?? 0)]), $product['variants']);
        if (! $store->show_models) {
            $product['device_models'] = [];
        }

        return $this->priced($store, [...$product, ...$this->availability($store, max([0, ...array_values($qty)]))]);
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

            return $this->priced($store, [...$item, ...$this->availability($store, $best)]);
        }, $items);
    }

    /**
     * «اسأل عن السعر»: the owner hides prices, so none leave the server.
     *
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function priced(OnlineStore $store, array $row): array
    {
        if (! $store->show_prices) {
            foreach (['price', 'price_max'] as $key) {
                if (array_key_exists($key, $row)) {
                    $row[$key] = null;
                }
            }
        }

        return $row;
    }

    /** @return array{availability: string, quantity?: int} */
    private function availability(OnlineStore $store, int $qty): array
    {
        $state = $qty <= 0 ? 'out' : ($qty <= self::LOW ? 'low' : 'in');

        return $store->show_quantity ? ['availability' => $state, 'quantity' => max(0, $qty)] : ['availability' => $state];
    }

    /**
     * Each variant's stock in the store's branch.
     *
     * @param  list<string>  $variantIds
     * @return array<string, int>
     */
    public function quantities(OnlineStore $store, array $variantIds): array
    {
        return $this->stock->quantities($this->branch($store), $variantIds);
    }

    public function branch(OnlineStore $store): string
    {
        return (string) ($store->branch_id ?? $this->branches->mainBranchId());
    }

    /** @return list<string>|null */
    private function onlyInStock(OnlineStore $store): ?array
    {
        return $store->show_out_of_stock ? null : $this->stock->inStock($this->branch($store));
    }
}
