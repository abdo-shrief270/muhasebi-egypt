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
        foreach (['category' => 'category', 'condition' => 'condition', 'governorate' => 'governorate', 'shop' => 'shop.slug'] as $param => $field) {
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

        $items = array_map(function (array $hit) use ($governorates, $hasPoint, $sort): array {
            $s = $hit['_source'];

            return [
                'id' => $hit['_id'],
                'variant_id' => $s['variant_id'] ?? null,
                'product_id' => $s['product_id'] ?? null,
                'title' => $s['title'] ?? '',
                'brand' => $s['brand'] ?? null,
                'category' => $s['category'] ?? null,
                'condition' => $s['condition'] ?? 'new',
                'grade' => $s['grade'] ?? null,
                'price' => (int) ($s['price'] ?? 0),
                'availability' => $s['availability'] ?? 'in',
                'image' => $s['image'] ?? null,
                'shop' => ['slug' => $s['shop']['slug'] ?? null, 'name' => $s['shop']['name'] ?? null],
                'place' => [
                    'governorate' => $s['governorate'] ?? null,
                    'governorate_label' => isset($s['governorate']) ? ($governorates[$s['governorate']] ?? null) : null,
                    'area' => $s['area'] ?? null,
                ],
                // The distance sort's value is the distance in km.
                'distance_km' => $hasPoint && $sort === 'nearest' ? round((float) ($hit['sort'][0] ?? 0), 1) : null,
            ];
        }, $result['hits']['hits'] ?? []);

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
