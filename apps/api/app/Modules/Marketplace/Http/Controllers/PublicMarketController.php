<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Identity\Contracts\Governorates;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Marketplace\Support\MarketSearch;
use App\Modules\OnlineStore\Contracts\PublicStores;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Search\SearchClient;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Throwable;

/** The marketplace site's read-only API (no login): search across every listed shop. */
final class PublicMarketController
{
    public function search(Request $request, MarketSearch $market, SearchClient $search): JsonResponse
    {
        $p = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'category' => ['nullable', Rule::in(array_keys(MarketSearch::CATEGORIES))],
            'condition' => ['nullable', Rule::in(array_keys(MarketSearch::CONDITIONS))],
            'governorate' => ['nullable', Rule::in(array_keys(Governorates::labels()))],
            'brand' => ['nullable', 'string', 'max:60'],
            'shop' => ['nullable', 'string', 'max:40'],
            'min' => ['nullable', 'integer', 'min:0'],
            'max' => ['nullable', 'integer', 'min:0'],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'sort' => ['nullable', Rule::in(MarketSearch::SORTS)],
            'page' => ['nullable', 'integer', 'min:1', 'max:100'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:48'],
        ]);
        if (! $search->enabled()) {
            throw new DomainRuleException('سوق محاسبي مش شغال دلوقتي.', 'market_unavailable', 503);
        }
        try {
            $result = $market->search($p);
        } catch (Throwable $e) {
            report($e);
            throw new DomainRuleException('البحث واقف دلوقتي، جرّب كمان شوية.', 'market_unavailable', 503);
        }

        return response()->json(['data' => $result])->header('Cache-Control', 'public, max-age=30');
    }

    /** Shops near the visitor (or in a governorate) with what they have in stock. */
    public function shops(Request $request, MarketSearch $market): JsonResponse
    {
        $p = $request->validate([
            'governorate' => ['nullable', Rule::in(array_keys(Governorates::labels()))],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);
        $rows = $this->guard(fn () => $market->shops(
            isset($p['lat']) ? (float) $p['lat'] : null, isset($p['lng']) ? (float) $p['lng'] : null,
            $p['governorate'] ?? null, limit: (int) ($p['limit'] ?? 30),
        ));

        return response()->json(['data' => $rows])->header('Cache-Control', 'public, max-age=60');
    }

    /** A shop's page: its branches on the map, how to reach it, its own store if open. */
    public function shop(Request $request, string $slug, MarketSearch $market, ShopDirectory $shops, PublicStores $stores): JsonResponse
    {
        $p = $request->validate([
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ]);
        $branches = $this->guard(fn () => $market->shops(isset($p['lat']) ? (float) $p['lat'] : null, isset($p['lng']) ? (float) $p['lng'] : null, slug: strtolower($slug), limit: 100));
        $shop = $branches !== [] ? $shops->findByCode(strtoupper($slug)) : null;
        abort_if($shop === null, 404);

        return response()->json(['data' => [
            'slug' => strtolower($shop->code),
            'name' => $shop->name,
            'phone' => $shop->phone,
            'store_url' => $stores->url($shop->id),
            'branches' => $branches,
            'offers' => array_sum(array_column($branches, 'offers')),
        ]])->header('Cache-Control', 'public, max-age=60');
    }

    /** One offer, and the same thing in other shops. */
    public function offer(Request $request, string $id, MarketSearch $market): JsonResponse
    {
        $p = $request->validate([
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ]);
        $data = $this->guard(fn () => $market->offer($id, isset($p['lat']) ? (float) $p['lat'] : null, isset($p['lng']) ? (float) $p['lng'] : null));
        abort_if($data === null, 404);

        return response()->json(['data' => $data])->header('Cache-Control', 'public, max-age=30');
    }

    /**
     * @template T
     *
     * @param  callable(): T  $fn
     * @return T
     */
    private function guard(callable $fn): mixed
    {
        if (! app(SearchClient::class)->enabled()) {
            throw new DomainRuleException('سوق محاسبي مش شغال دلوقتي.', 'market_unavailable', 503);
        }
        try {
            return $fn();
        } catch (Throwable $e) {
            report($e);
            throw new DomainRuleException('البحث واقف دلوقتي، جرّب كمان شوية.', 'market_unavailable', 503);
        }
    }

    /** What the search page's filters offer before anything is typed. */
    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'categories' => self::pairs(MarketSearch::CATEGORIES),
            'conditions' => self::pairs(MarketSearch::CONDITIONS),
            'governorates' => self::pairs(Governorates::labels()),
            'sorts' => MarketSearch::SORTS,
        ]])->header('Cache-Control', 'public, max-age=3600');
    }

    /**
     * @param  array<string, string>  $labels
     * @return list<array{key: string, label: string}>
     */
    private static function pairs(array $labels): array
    {
        return array_map(fn (string $k, string $l) => ['key' => $k, 'label' => $l], array_keys($labels), $labels);
    }
}
