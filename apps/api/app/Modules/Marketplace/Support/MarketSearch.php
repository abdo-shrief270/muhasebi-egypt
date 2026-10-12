<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Support;

use App\Modules\Identity\Contracts\Governorates;
use App\Support\Search\MarketIndices;
use App\Support\Search\SearchClient;

/**
 * The marketplace's search: offers from every listed shop, filtered, sorted and counted per
 * filter (facets), one card per variant of a shop (its nearest / first branch).
 */
final class MarketSearch
{
    public const SORTS = ['relevance', 'price_asc', 'price_desc', 'nearest', 'new'];

    public const CATEGORIES = [
        'accessory' => 'إكسسوارات',
        'part' => 'قطع غيار',
        'device' => 'موبايلات جديدة',
        'used' => 'مستعمل',
        'other' => 'حاجات تانية',
    ];

    public const CONDITIONS = ['new' => 'جديد', 'used' => 'مستعمل'];

    public function __construct(private readonly SearchClient $search) {}

    /**
     * @param  array{q?: string|null, category?: string|null, condition?: string|null, governorate?: string|null, brand?: string|null, shop?: string|null, min?: int|null, max?: int|null, lat?: float|null, lng?: float|null, sort?: string|null, page?: int, per_page?: int}  $p
     * @return array{items: list<array<string, mixed>>, total: int, page: int, per_page: int, facets: array<string, list<array{key: string, label: string, count: int}>>, price: array{min: int|null, max: int|null}}
     */
    public function search(array $p): array
    {
        $page = max(1, (int) ($p['page'] ?? 1));
        $perPage = min(48, max(1, (int) ($p['per_page'] ?? 24)));
        $q = trim((string) ($p['q'] ?? ''));
        $hasPoint = isset($p['lat'], $p['lng']);
        $sort = in_array($p['sort'] ?? null, self::SORTS, true) ? $p['sort'] : ($q !== '' ? 'relevance' : ($hasPoint ? 'nearest' : 'new'));
        if ($sort === 'nearest' && ! $hasPoint) {
            $sort = 'relevance';
        }

        $filters = [];
        foreach (['category' => 'category', 'condition' => 'condition', 'governorate' => 'governorate', 'shop' => 'shop.slug', 'item' => 'item_id', 'fits' => 'fits'] as $param => $field) {
            if (($p[$param] ?? null) !== null && $p[$param] !== '') {
                $filters[] = ['term' => [$field => $p[$param]]];
            }
        }
        if (($p['brand'] ?? null) !== null && $p['brand'] !== '') {
            $filters[] = ['term' => ['brand.raw' => mb_strtolower((string) $p['brand'])]];
        }
        $range = array_map('intval', array_filter(['gte' => $p['min'] ?? null, 'lte' => $p['max'] ?? null], fn ($v) => $v !== null && $v !== ''));
        if ($range !== []) {
            $filters[] = ['range' => ['price' => $range]];
        }

        $must = $q === '' ? ['match_all' => (object) []] : ['bool' => [
            'should' => [
                // Every word, with the synonyms (ايفون = iphone, جراب = كفر…)
                ['multi_match' => ['query' => $q, 'fields' => ['title^3', 'models^2', 'brand^2', 'category_name', 'description^0.3'], 'operator' => 'and', 'boost' => 2]],
                // Typing as you go: «ايفو» finds iPhone
                ['multi_match' => ['query' => $q, 'type' => 'bool_prefix', 'fields' => ['title.suggest', 'title.suggest._2gram', 'title.suggest._3gram'], 'operator' => 'and']],
                // A typo: «ايفوون», «سامسنج»
                ['match' => ['title' => ['query' => $q, 'operator' => 'and', 'fuzziness' => 'AUTO', 'prefix_length' => 1, 'analyzer' => 'ar_prefix', 'boost' => 0.5]]],
            ],
            'minimum_should_match' => 1,
        ]];

        $body = [
            'query' => ['function_score' => [
                'query' => ['bool' => ['must' => [$must], 'filter' => $filters]],
                // Plenty in stock ranks a little above «قرب يخلص»; offers with a photo above those without.
                'functions' => [
                    ['filter' => ['term' => ['availability' => 'in']], 'weight' => 1.2],
                    ['filter' => ['range' => ['images' => ['gte' => 1]]], 'weight' => 1.3],
                ],
                'score_mode' => 'multiply',
                'boost_mode' => 'multiply',
            ]],
            // One card per variant of a shop; its other branches don't repeat it.
            'collapse' => ['field' => 'variant_id'],
            'from' => ($page - 1) * $perPage,
            'size' => $perPage,
            'track_total_hits' => true,
            'sort' => $this->sort($sort, $p),
            'aggs' => [
                'category' => ['terms' => ['field' => 'category', 'size' => 10]],
                'condition' => ['terms' => ['field' => 'condition', 'size' => 5]],
                'governorate' => ['terms' => ['field' => 'governorate', 'size' => 30]],
                'brand' => ['terms' => ['field' => 'brand.raw', 'size' => 30]],
                'price_min' => ['min' => ['field' => 'price']],
                'price_max' => ['max' => ['field' => 'price']],
            ],
        ];

        $result = $this->search->search($this->search->name(MarketIndices::OFFERS), $body);
        $governorates = Governorates::labels();

        $point = $hasPoint ? [(float) $p['lat'], (float) $p['lng']] : null;
        $items = array_map(fn (array $hit) => $this->present($hit, $governorates, $point), $result['hits']['hits'] ?? []);

        $aggs = $result['aggregations'] ?? [];
        $labels = [
            'category' => self::CATEGORIES,
            'condition' => self::CONDITIONS,
            'governorate' => $governorates,
            'brand' => [],
        ];
        $facets = [];
        foreach ($labels as $name => $names) {
            $facets[$name] = array_map(fn (array $b) => [
                'key' => (string) $b['key'],
                'label' => $names[$b['key']] ?? (string) $b['key'],
                'count' => (int) $b['doc_count'],
            ], $aggs[$name]['buckets'] ?? []);
        }

        return [
            'items' => $items,
            'total' => (int) ($result['hits']['total']['value'] ?? 0),
            'page' => $page,
            'per_page' => $perPage,
            'sort' => $sort,
            'facets' => $facets,
            'price' => [
                'min' => isset($aggs['price_min']['value']) ? (int) $aggs['price_min']['value'] : null,
                'max' => isset($aggs['price_max']['value']) ? (int) $aggs['price_max']['value'] : null,
            ],
        ];
    }

    /**
     * Shops near a point (or in a governorate) with what they have in stock: one row per listed
     * branch with offers, nearest first when there's a point, else the most offers first.
     *
     * @return list<array{slug: string, name: string, branch_id: string, governorate: ?string, governorate_label: ?string, area: ?string, location: ?array{lat: float, lng: float}, offers: int, distance_km: ?float}>
     */
    public function shops(?float $lat, ?float $lng, ?string $governorate = null, ?string $slug = null, int $limit = 30): array
    {
        $filters = [];
        if ($governorate !== null) {
            $filters[] = ['term' => ['governorate' => $governorate]];
        }
        if ($slug !== null) {
            $filters[] = ['term' => ['shop.slug' => $slug]];
        }
        $result = $this->search->search($this->search->name(MarketIndices::OFFERS), [
            'size' => 0,
            'query' => ['bool' => ['filter' => $filters]],
            'aggs' => ['branches' => [
                'terms' => ['field' => 'branch_id', 'size' => 1000],
                'aggs' => ['shop' => ['top_hits' => ['size' => 1, '_source' => ['shop', 'governorate', 'area', 'location', 'branch_id']]]],
            ]],
        ]);
        $governorates = Governorates::labels();
        $point = $lat !== null && $lng !== null ? [$lat, $lng] : null;
        $rows = [];
        foreach ($result['aggregations']['branches']['buckets'] ?? [] as $bucket) {
            $s = $bucket['shop']['hits']['hits'][0]['_source'] ?? null;
            if ($s === null || ! isset($s['shop']['slug'])) {
                continue;
            }
            $location = self::location($s);
            $rows[] = [
                'slug' => $s['shop']['slug'],
                'name' => $s['shop']['name'] ?? '',
                'branch_id' => (string) $bucket['key'],
                'governorate' => $s['governorate'] ?? null,
                'governorate_label' => isset($s['governorate']) ? ($governorates[$s['governorate']] ?? null) : null,
                'area' => $s['area'] ?? null,
                'location' => $location,
                'offers' => (int) $bucket['doc_count'],
                'distance_km' => $point !== null && $location !== null ? round(self::km($point, [$location['lat'], $location['lng']]), 1) : null,
            ];
        }
        usort($rows, fn (array $a, array $b) => $point !== null
            ? [($a['distance_km'] ?? INF), -$a['offers']] <=> [($b['distance_km'] ?? INF), -$b['offers']]
            : [-$a['offers'], $a['name']] <=> [-$b['offers'], $b['name']]);

        return array_slice($rows, 0, $limit);
    }

    /**
     * One offer, and the same thing elsewhere: the closest matches by name in other shops (nearest
     * first with a point, else cheapest).
     *
     * @return array{offer: array<string, mixed>, elsewhere: list<array<string, mixed>>}|null
     */
    public function offer(string $id, ?float $lat, ?float $lng): ?array
    {
        $index = $this->search->name(MarketIndices::OFFERS);
        $hit = $this->search->search($index, ['size' => 1, 'query' => ['ids' => ['values' => [$id]]]])['hits']['hits'][0] ?? null;
        if ($hit === null) {
            return null;
        }
        $governorates = Governorates::labels();
        $point = $lat !== null && $lng !== null ? [$lat, $lng] : null;
        $offer = $this->present($hit, $governorates, $point);
        $s = $hit['_source'];
        $offer['description'] = $s['description'] ?? null;
        $offer['images'] = $s['images'] ?? [];
        $offer['models'] = $s['models'] ?? [];
        $offer['category_name'] = $s['category_name'] ?? null;

        $similar = $this->search->search($index, [
            'size' => 12,
            'query' => ['bool' => [
                'must' => [['match' => ['title' => ['query' => (string) ($s['title'] ?? ''), 'operator' => 'and']]]],
                'filter' => [['term' => ['condition' => $s['condition'] ?? 'new']]],
                'must_not' => [['term' => ['shop.slug' => $s['shop']['slug'] ?? '']]],
            ]],
            'collapse' => ['field' => 'variant_id'],
            'sort' => $point !== null
                ? [['_geo_distance' => ['location' => ['lat' => $point[0], 'lon' => $point[1]], 'order' => 'asc', 'unit' => 'km', 'ignore_unmapped' => true]]]
                : [['price' => 'asc']],
        ]);

        return [
            'offer' => $offer,
            'elsewhere' => array_map(fn (array $h) => $this->present($h, $governorates, $point), $similar['hits']['hits'] ?? []),
        ];
    }

    /**
     * Prices of catalog phones across every listed shop: per phone and condition, the lowest /
     * highest price, how many offers (one per variant of a shop) and shops.
     *
     * @param  list<string>|null  $phoneIds  null = every phone with offers
     * @return array<string, array{new: array{min: int, max: int, offers: int, shops: int}|null, used: array{min: int, max: int, offers: int, shops: int}|null, fits: int}>
     */
    public function phonePrices(?array $phoneIds = null): array
    {
        $index = $this->search->name(MarketIndices::OFFERS);
        $filter = $phoneIds === null ? [['exists' => ['field' => 'item_id']]] : [['terms' => ['item_id' => array_values($phoneIds)]]];
        $result = $this->search->search($index, [
            'size' => 0,
            'query' => ['bool' => ['filter' => $filter]],
            'aggs' => ['phones' => [
                'terms' => ['field' => 'item_id', 'size' => 2000],
                'aggs' => ['condition' => [
                    'terms' => ['field' => 'condition', 'size' => 2],
                    'aggs' => [
                        'min' => ['min' => ['field' => 'price']],
                        'max' => ['max' => ['field' => 'price']],
                        'offers' => ['cardinality' => ['field' => 'variant_id']],
                        'shops' => ['cardinality' => ['field' => 'tenant_id']],
                    ],
                ]],
            ]],
        ]);
        $fits = $this->search->search($index, [
            'size' => 0,
            'query' => ['bool' => ['filter' => $phoneIds === null ? [['exists' => ['field' => 'fits']]] : [['terms' => ['fits' => array_values($phoneIds)]]]]],
            'aggs' => ['phones' => ['terms' => ['field' => 'fits', 'size' => 2000], 'aggs' => ['offers' => ['cardinality' => ['field' => 'variant_id']]]]],
        ]);

        $out = [];
        foreach ($result['aggregations']['phones']['buckets'] ?? [] as $bucket) {
            $row = ['new' => null, 'used' => null, 'fits' => 0];
            foreach ($bucket['condition']['buckets'] ?? [] as $c) {
                $row[$c['key'] === 'used' ? 'used' : 'new'] = [
                    'min' => (int) $c['min']['value'],
                    'max' => (int) $c['max']['value'],
                    'offers' => (int) $c['offers']['value'],
                    'shops' => (int) $c['shops']['value'],
                ];
            }
            $out[(string) $bucket['key']] = $row;
        }
        foreach ($fits['aggregations']['phones']['buckets'] ?? [] as $bucket) {
            $id = (string) $bucket['key'];
            if ($phoneIds !== null && ! in_array($id, $phoneIds, true)) {
                continue;
            }
            $out[$id] ??= ['new' => null, 'used' => null, 'fits' => 0];
            $out[$id]['fits'] = (int) $bucket['offers']['value'];
        }

        return $out;
    }

    /**
     * @param  array<string, mixed>  $hit
     * @param  array<string, string>  $governorates
     * @param  array{0: float, 1: float}|null  $point
     * @return array<string, mixed>
     */
    private function present(array $hit, array $governorates, ?array $point): array
    {
        $s = $hit['_source'];
        $location = self::location($s);

        return [
            'id' => $hit['_id'],
            'variant_id' => $s['variant_id'] ?? null,
            'product_id' => $s['product_id'] ?? null,
            'title' => $s['title'] ?? '',
            'brand' => $s['brand'] ?? null,
            'category' => $s['category'] ?? null,
            'condition' => $s['condition'] ?? 'new',
            'grade' => $s['grade'] ?? null,
            'item_id' => $s['item_id'] ?? null,
            'storage_gb' => $s['storage_gb'] ?? null,
            'price' => (int) ($s['price'] ?? 0),
            'availability' => $s['availability'] ?? 'in',
            'image' => $s['image'] ?? null,
            'shop' => ['slug' => $s['shop']['slug'] ?? null, 'name' => $s['shop']['name'] ?? null],
            'place' => [
                'governorate' => $s['governorate'] ?? null,
                'governorate_label' => isset($s['governorate']) ? ($governorates[$s['governorate']] ?? null) : null,
                'area' => $s['area'] ?? null,
            ],
            // Shops are public places: the branch's spot is shown on the map.
            'location' => $location,
            'distance_km' => $point !== null && $location !== null ? round(self::km($point, [$location['lat'], $location['lng']]), 1) : null,
        ];
    }

    /** @return array{lat: float, lng: float}|null */
    private static function location(array $s): ?array
    {
        $l = $s['location'] ?? null;

        return is_array($l) && isset($l['lat'], $l['lon']) ? ['lat' => (float) $l['lat'], 'lng' => (float) $l['lon']] : null;
    }

    /**
     * Great-circle distance in km.
     *
     * @param  array{0: float, 1: float}  $a
     * @param  array{0: float, 1: float}  $b
     */
    private static function km(array $a, array $b): float
    {
        [$lat1, $lng1, $lat2, $lng2] = array_map('deg2rad', [$a[0], $a[1], $b[0], $b[1]]);
        $h = sin(($lat2 - $lat1) / 2) ** 2 + cos($lat1) * cos($lat2) * sin(($lng2 - $lng1) / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($h)));
    }

    /**
     * @param  array<string, mixed>  $p
     * @return list<array<string, mixed>>
     */
    private function sort(string $sort, array $p): array
    {
        return match ($sort) {
            'price_asc' => [['price' => 'asc'], '_score'],
            'price_desc' => [['price' => 'desc'], '_score'],
            'new' => [['listed_at' => 'desc'], '_score'],
            'nearest' => [['_geo_distance' => ['location' => ['lat' => (float) $p['lat'], 'lon' => (float) $p['lng']], 'order' => 'asc', 'unit' => 'km', 'ignore_unmapped' => true]], '_score'],
            default => ['_score', ['listed_at' => 'desc']],
        };
    }
}
