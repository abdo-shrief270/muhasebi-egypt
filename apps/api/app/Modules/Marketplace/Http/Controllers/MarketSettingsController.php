<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Billing\Contracts\SubscriptionStanding;
use App\Modules\Catalog\Contracts\MarketCatalog;
use App\Modules\Catalog\Contracts\StorefrontCatalog;
use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Marketplace\Jobs\ReindexShop;
use App\Modules\Marketplace\Models\MarketSettings;
use App\Modules\Marketplace\Support\MarketIndexer;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Throwable;

/**
 * «سوق محاسبي» for the shop (marketplace.manage): every shop is listed on its own; here it sees
 * how it shows and hides itself, a branch, a category or a product.
 */
final class MarketSettingsController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly BranchDirectory $branches,
        private readonly MarketCatalog $catalog,
        private readonly StorefrontCatalog $categories,
        private readonly SubscriptionStanding $standing,
        private readonly MarketIndexer $indexer,
    ) {}

    public function show(): JsonResponse
    {
        return response()->json(['data' => $this->present(MarketSettings::current())]);
    }

    public function update(Request $request, Auditor $audit): JsonResponse
    {
        $data = $request->validate([
            'listed' => ['sometimes', 'boolean'],
            'hidden_branches' => ['sometimes', 'array', 'max:100'],
            'hidden_branches.*' => ['string', Rule::in(array_keys($this->branches->locations()))],
            'hidden_categories' => ['sometimes', 'array', 'max:500'],
            'hidden_categories.*' => ['integer', Rule::in(array_keys($this->categories->categoryNames()))],
            'hidden_products' => ['sometimes', 'array', 'max:2000'],
            'hidden_products.*' => ['uuid'],
        ]);
        if (isset($data['hidden_products'])) {
            // Only the shop's own products stay on the list.
            $data['hidden_products'] = array_keys($this->catalog->productNames(array_values(array_unique($data['hidden_products']))));
        }
        foreach (['hidden_branches', 'hidden_categories'] as $key) {
            if (isset($data[$key])) {
                $data[$key] = array_values(array_unique($data[$key]));
            }
        }

        $settings = DB::transaction(function () use ($data, $audit): MarketSettings {
            $settings = MarketSettings::query()->lockForUpdate()->first() ?? new MarketSettings(['tenant_id' => $this->tenant->idOrFail()]);
            $wasListed = $settings->listed;
            $settings->fill($data)->save();
            $audit->record(
                'marketplace.settings',
                match (true) {
                    $wasListed && ! $settings->listed => 'خفى المحل من سوق محاسبي',
                    ! $wasListed && $settings->listed => 'رجّع المحل في سوق محاسبي',
                    default => 'عدّل اللي بيظهر في سوق محاسبي',
                },
                $settings,
            );

            return $settings;
        });
        ReindexShop::dispatch($this->tenant->idOrFail())->afterCommit();

        return response()->json(['data' => $this->present($settings)]);
    }

    /** @return array<string, mixed> */
    private function present(MarketSettings $settings): array
    {
        $tenantId = $this->tenant->idOrFail();
        $locations = $this->branches->locations();
        $offers = null;
        if ($this->indexer->enabled()) {
            try {
                $offers = $this->indexer->countFor($tenantId);
            } catch (Throwable) {
                $offers = null; // the cluster is down: the screen still works
            }
        }

        return [
            'listed' => $settings->listed,
            'hidden_branches' => $settings->hidden_branches,
            'hidden_categories' => array_map('intval', $settings->hidden_categories),
            'hidden_products' => collect($this->catalog->productNames($settings->hidden_products))
                ->map(fn (string $name, string $id) => ['id' => $id, 'name' => $name])->values()->all(),
            'status' => [
                'subscription' => $this->standing->status($tenantId),
                'subscription_ok' => $this->standing->listed($tenantId),
                'live' => (string) config('services.market.url') !== '',
                'url' => (string) config('services.market.url') ?: null,
                'search' => $this->indexer->enabled(),
                'offers' => $offers,
            ],
            'branches' => collect($locations)->map(fn (array $b, string $id) => [
                'id' => $id,
                'name' => $b['name'],
                'governorate_label' => $b['governorate_label'],
                'area' => $b['area'],
                'located' => $b['latitude'] !== null && $b['longitude'] !== null,
                'has_governorate' => $b['governorate'] !== null,
            ])->values()->all(),
            'categories' => collect($this->categories->categoryNames())
                ->map(fn (string $name, int $id) => ['id' => $id, 'name' => $name])->values()->all(),
        ];
    }
}
