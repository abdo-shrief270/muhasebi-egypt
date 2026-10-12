<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Http\Controllers;

use App\Modules\Identity\Contracts\Governorates;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Marketplace\Contracts\MarketModeration;
use App\Modules\Marketplace\Contracts\PhoneCatalog;
use App\Modules\Marketplace\Support\MarketSearch;
use App\Modules\Marketplace\Support\RepairShops;
use App\Modules\OnlineStore\Contracts\PublicStores;
use App\Modules\OnlineStore\Contracts\ShopReputation;
use App\Modules\UsedDevices\Contracts\UsedListings;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Search\SearchClient;
use App\Support\Tenancy\CurrentTenant;
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
            'grade' => ['nullable', 'array', 'max:3'],
            'grade.*' => [Rule::in(MarketSearch::GRADES)],
            'battery' => ['nullable', 'integer', 'min:50', 'max:100'],
            'storage' => ['nullable', 'integer', Rule::in([16, 32, 64, 128, 256, 512, 1024, 2048])],
            'phone' => ['nullable', 'string', 'max:140'],   // offers that ARE this catalog phone (slug)
            'fits' => ['nullable', 'string', 'max:140'],    // accessories / parts that fit it (slug)
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
        $phones = app(PhoneCatalog::class);
        foreach (['phone' => 'item', 'fits' => 'fits'] as $param => $filter) {
            if (($p[$param] ?? null) !== null) {
                // An unknown phone filters to nothing rather than to everything.
                $p[$filter] = $phones->findBySlug((string) $p[$param])['id'] ?? 'none';
            }
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
    public function shop(Request $request, string $slug, MarketSearch $market, ShopDirectory $shops, PublicStores $stores, ShopReputation $reputation): JsonResponse
    {
        $p = $request->validate([
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ]);
        $branches = $this->guard(fn () => $market->shops(isset($p['lat']) ? (float) $p['lat'] : null, isset($p['lng']) ? (float) $p['lng'] : null, slug: strtolower($slug), limit: 100));
        $shop = $branches !== [] ? $shops->findByCode(strtoupper($slug)) : null;
        abort_if($shop === null, 404);
        $store = $stores->find($shop->id);

        return response()->json(['data' => [
            'slug' => strtolower($shop->code),
            'name' => $shop->name,
            'phone' => $shop->phone,
            // Its own store: a link, and — when it takes orders — where the market's cart orders.
            'store' => $store,
            'store_url' => $store['url'] ?? null,
            'branches' => $branches,
            'offers' => array_sum(array_column($branches, 'offers')),
            // From delivered orders only.
            'reputation' => $reputation->scores([$shop->id])[$shop->id] ?? ['rating' => null, 'reviews' => 0, 'response' => null],
            'reviews' => $reputation->latest($shop->id, 10),
        ]])->header('Cache-Control', 'public, max-age=60');
    }

    /** «في حاجة غلط في العرض ده»: a visitor's report, for the platform admins. */
    public function report(Request $request, MarketSearch $market, MarketModeration $moderation, SearchClient $search): JsonResponse
    {
        $data = $request->validate([
            'offer_id' => ['required', 'string', 'regex:/^[A-Za-z0-9:_-]{1,120}$/'],
            'reason' => ['required', Rule::in(array_keys(MarketModeration::REASONS))],
            'note' => ['nullable', 'string', 'max:500'],
            'contact' => ['nullable', 'string', 'max:30'],
            'website' => ['nullable', 'max:0'],
        ], [], ['reason' => 'السبب', 'note' => 'التفاصيل']);
        $offer = null;
        if ($search->enabled()) {
            try {
                $offer = $market->document($data['offer_id']);
            } catch (Throwable) {
                $offer = null; // the report still counts
            }
        }
        $moderation->report($data['offer_id'], $offer, $data['reason'], $data['note'] ?? null, $data['contact'] ?? null, (string) $request->ip());

        return response()->json(['data' => ['ok' => true]], 201)->header('Cache-Control', 'no-store');
    }

    /** Shops near the visitor that take repair bookings through their store. */
    public function repairShops(Request $request, RepairShops $shops): JsonResponse
    {
        $p = $request->validate([
            'governorate' => ['nullable', Rule::in(array_keys(Governorates::labels()))],
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ]);

        return response()->json(['data' => $shops->near(
            isset($p['lat']) ? (float) $p['lat'] : null, isset($p['lng']) ? (float) $p['lng'] : null, $p['governorate'] ?? null,
        )])->header('Cache-Control', 'public, max-age=60');
    }

    /** One offer, and the same thing in other shops. */
    public function offer(Request $request, string $id, MarketSearch $market, PhoneCatalog $phones, UsedListings $used, CurrentTenant $tenant): JsonResponse
    {
        $p = $request->validate([
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ]);
        $data = $this->guard(fn () => $market->offer($id, isset($p['lat']) ? (float) $p['lat'] : null, isset($p['lng']) ? (float) $p['lng'] : null));
        abort_if($data === null, 404);
        // The catalog phone it is: the page links to «سعره في كل المحلات».
        $phone = $data['offer']['item_id'] ? ($phones->many([$data['offer']['item_id']])[$data['offer']['item_id']] ?? null) : null;
        $data['phone'] = $phone !== null && $phone['status'] === 'published' ? self::phoneCard($phone) : null;
        // A used device: its grade, battery, inspection and photos (never the seller or the IMEI).
        $tenantId = $data['offer']['tenant_id'];
        unset($data['offer']['tenant_id']);
        $data['used'] = $data['offer']['condition'] === 'used' && $tenantId !== null && $data['offer']['variant_id'] !== null
            ? ($tenant->runAs($tenantId, fn () => $used->forVariants([$data['offer']['variant_id']]))[$data['offer']['variant_id']] ?? null)
            : null;
        if (is_array($data['used'])) {
            unset($data['used']['device_id']);
        }

        return response()->json(['data' => $data])->header('Cache-Control', 'public, max-age=30');
    }

    /**
     * Phone prices («أسعار الموبايلات»): every published catalog phone with its lowest / highest
     * price new and used across the listed shops.
     */
    public function phones(Request $request, MarketSearch $market, PhoneCatalog $phones): JsonResponse
    {
        $p = $request->validate(['brand' => ['nullable', 'string', 'max:40']]);
        $list = $phones->published();
        if (($p['brand'] ?? null) !== null) {
            $list = array_values(array_filter($list, fn (array $phone) => mb_strtolower($phone['brand']) === mb_strtolower((string) $p['brand'])));
        }
        $prices = $this->guard(fn () => $market->phonePrices());
        $rows = array_map(fn (array $phone) => [...self::phoneCard($phone), 'prices' => $prices[$phone['id']] ?? ['new' => null, 'used' => null, 'fits' => 0]], $list);
        $brands = array_count_values(array_column($phones->published(), 'brand'));
        arsort($brands);

        return response()->json(['data' => [
            'phones' => $rows,
            'brands' => array_map(fn (string $b, int $n) => ['key' => $b, 'count' => $n], array_keys($brands), $brands),
        ]])->header('Cache-Control', 'public, max-age=300');
    }

    /** One phone: its prices in every shop, new and used, and what fits it. */
    public function phone(Request $request, string $slug, MarketSearch $market, PhoneCatalog $phones): JsonResponse
    {
        $p = $request->validate([
            'lat' => ['nullable', 'required_with:lng', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'required_with:lat', 'numeric', 'between:-180,180'],
        ]);
        $phone = $phones->findBySlug($slug);
        abort_if($phone === null, 404);
        $point = isset($p['lat'], $p['lng']) ? ['lat' => (float) $p['lat'], 'lng' => (float) $p['lng']] : [];
        [$prices, $new, $used] = $this->guard(fn () => [
            $market->phonePrices([$phone['id']])[$phone['id']] ?? ['new' => null, 'used' => null, 'fits' => 0],
            $market->search([...$point, 'item' => $phone['id'], 'condition' => 'new', 'sort' => $point ? 'nearest' : 'price_asc', 'per_page' => 48]),
            $market->search([...$point, 'item' => $phone['id'], 'condition' => 'used', 'sort' => $point ? 'nearest' : 'price_asc', 'per_page' => 24]),
        ]);

        return response()->json(['data' => [
            'phone' => [...self::phoneCard($phone), 'aliases' => $phone['aliases']],
            'prices' => $prices,
            'new' => $new['items'],
            'used' => $used['items'],
        ]])->header('Cache-Control', 'public, max-age=60');
    }

    /**
     * @param  array<string, mixed>  $phone
     * @return array<string, mixed>
     */
    private static function phoneCard(array $phone): array
    {
        return array_intersect_key($phone, array_flip(['slug', 'brand', 'series', 'model', 'name', 'year', 'storage_gb', 'network']));
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
            'grades' => self::pairs(MarketSearch::GRADE_LABELS),
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
