<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Billing\Contracts\SubscriptionStanding;
use App\Modules\Catalog\Contracts\MarketCatalog;
use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Marketplace\Models\MarketSettings;
use App\Support\Search\MarketIndices;
use App\Support\Search\SearchClient;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;

/**
 * Copies what shops offer into the marketplace's search index: one document per (variant ×
 * branch) in stock, from the shop's catalog, stock and branches. Postgres stays the truth; the
 * index is rebuilt from it every night and patched in between (market:sync).
 */
final class MarketIndexer
{
    /** At or under this many in a branch, the offer shows «قرب يخلص». */
    public const LOW = 2;

    public function __construct(
        private readonly SearchClient $search,
        private readonly CurrentTenant $tenant,
        private readonly SubscriptionStanding $standing,
        private readonly MarketCatalog $catalog,
        private readonly StockLedger $stock,
        private readonly BranchDirectory $branches,
        private readonly ShopDirectory $shops,
    ) {}

    public function enabled(): bool
    {
        return $this->search->enabled();
    }

    public function alias(): string
    {
        return $this->search->name(MarketIndices::OFFERS);
    }

    /** Whether the shop shows in the marketplace at all: its switch, and a subscription in good standing. */
    public function listed(string $tenantId): bool
    {
        return $this->tenant->runAs($tenantId, fn () => MarketSettings::current()->listed) && $this->standing->listed($tenantId);
    }

    /**
     * The documents for some of a shop's variants (all when null): id => document, or null where
     * that variant / branch must leave the index.
     *
     * @param  list<string>|null  $variantIds
     * @return array<string, array<string, mixed>|null>
     */
    public function documents(string $tenantId, ?array $variantIds = null): array
    {
        return $this->tenant->runAs($tenantId, function () use ($tenantId, $variantIds): array {
            $settings = MarketSettings::current();
            $shop = $this->shops->find($tenantId);
            $locations = $this->branches->locations();
            if ($shop === null || $locations === []) {
                return [];
            }
            $hiddenBranches = array_flip($settings->hidden_branches);
            $hiddenCategories = array_flip(array_map('intval', $settings->hidden_categories));
            $hiddenProducts = array_flip($settings->hidden_products);

            $offers = array_values(array_filter(
                $this->catalog->offers($variantIds),
                fn (array $o) => ! isset($hiddenCategories[$o['category']['id']]) && ! isset($hiddenProducts[$o['product_id']]),
            ));
            $shown = array_column($offers, 'variant_id');

            $docs = [];
            // Asked-for variants that are no longer offered (inactive, hidden, no price) leave every branch.
            foreach (array_diff($variantIds ?? [], $shown) as $gone) {
                foreach (array_keys($locations) as $branchId) {
                    $docs["{$gone}:{$branchId}"] = null;
                }
            }

            $now = Carbon::now()->toIso8601String();
            foreach ($locations as $branchId => $branch) {
                $quantities = $shown === [] ? [] : $this->stock->quantities($branchId, $shown);
                foreach ($offers as $offer) {
                    $id = "{$offer['variant_id']}:{$branchId}";
                    $qty = $quantities[$offer['variant_id']] ?? 0;
                    if (isset($hiddenBranches[$branchId]) || $qty <= 0) {
                        $docs[$id] = null;

                        continue;
                    }
                    $docs[$id] = [
                        'tenant_id' => $tenantId,
                        'branch_id' => $branchId,
                        'product_id' => $offer['product_id'],
                        'variant_id' => $offer['variant_id'],
                        'title' => $offer['title'],
                        'description' => $offer['description'],
                        'brand' => $offer['brand'],
                        'models' => $offer['models'],
                        'category' => $offer['used'] ? 'used' : $offer['category']['type'],
                        'category_name' => $offer['category']['name'],
                        'condition' => $offer['used'] ? 'used' : 'new',
                        'grade' => $offer['quality'],
                        'price' => $offer['price'],
                        'availability' => $offer['used'] || $qty > self::LOW ? 'in' : 'low',
                        'image' => $offer['image'][800] ?? null,
                        'images' => $offer['images'],
                        'shop' => [
                            'slug' => strtolower($shop->code),
                            'name' => $shop->name,
                            'delivers' => false,
                            'verified' => false,
                        ],
                        'location' => $branch['latitude'] !== null && $branch['longitude'] !== null
                            ? ['lat' => $branch['latitude'], 'lon' => $branch['longitude']] : null,
                        'governorate' => $branch['governorate'],
                        'area' => $branch['area'],
                        'listed_at' => $offer['updated_at'] ?? $now,
                        'updated_at' => $now,
                    ];
                }
            }

            return $docs;
        });
    }

    /**
     * Brings some variants of a shop up to date (market:sync). A shop that isn't listed is left
     * alone here: its offers leave with the next rebuild or its settings' reindex.
     *
     * @param  list<string>  $variantIds
     * @return int documents that failed
     */
    public function syncVariants(string $tenantId, array $variantIds): int
    {
        if ($variantIds === [] || ! $this->listed($tenantId)) {
            return 0;
        }

        return $this->search->bulk($this->alias(), $this->documents($tenantId, array_values(array_unique($variantIds))));
    }

    /** All of a shop's offers again (its settings changed): out first, then back in if it's listed. */
    public function reindexShop(string $tenantId): int
    {
        $this->removeShop($tenantId);
        if (! $this->listed($tenantId)) {
            return 0;
        }

        return $this->search->bulk($this->alias(), array_filter($this->documents($tenantId)), refresh: true);
    }

    public function removeShop(string $tenantId): void
    {
        $this->search->deleteByQuery($this->alias(), ['term' => ['tenant_id' => $tenantId]], refresh: true);
    }

    /** How many offers a shop has in the index now (its settings screen). */
    public function countFor(string $tenantId): int
    {
        return $this->search->count($this->alias(), ['term' => ['tenant_id' => $tenantId]]);
    }

    /**
     * The whole index from scratch into a new physical index, then the alias moves to it at once
     * and the old one goes: the site never searches a half-built index.
     *
     * @return array{index: string, shops: int, offers: int, failed: int}
     */
    public function rebuild(): array
    {
        $alias = $this->alias();
        $index = $alias.'_v'.MarketIndices::VERSION.'_'.Carbon::now()->format('YmdHis');
        $old = $this->search->aliasTargets($alias);
        $this->search->createIndex($index, MarketIndices::all()[MarketIndices::OFFERS]);

        $shops = $offers = $failed = 0;
        foreach ($this->standing->listedTenants() as $tenantId) {
            if (! $this->tenant->runAs($tenantId, fn () => MarketSettings::current()->listed)) {
                continue;
            }
            $docs = array_filter($this->documents($tenantId));
            foreach (array_chunk($docs, 1000, true) as $chunk) {
                $failed += $this->search->bulk($index, $chunk);
            }
            $shops++;
            $offers += count($docs);
        }

        $this->search->swapAlias($alias, $index);
        foreach ($old as $previous) {
            if ($previous !== $index) {
                $this->search->deleteIndex($previous);
            }
        }

        return ['index' => $index, 'shops' => $shops, 'offers' => $offers, 'failed' => $failed];
    }
}
