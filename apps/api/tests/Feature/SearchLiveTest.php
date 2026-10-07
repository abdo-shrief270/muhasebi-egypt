<?php

namespace Tests\Feature;

use App\Support\Search\MarketIndices;
use App\Support\Search\SearchClient;
use Tests\TestCase;

/**
 * The index definitions and the Arabic analysis against a real Elasticsearch: runs only when
 * SEARCH_LIVE_URL is set (the CI `search` job starts one with the production memory limits).
 */
class SearchLiveTest extends TestCase
{
    private SearchClient $search;

    protected function setUp(): void
    {
        parent::setUp();
        $url = (string) getenv('SEARCH_LIVE_URL');
        if ($url === '') {
            $this->markTestSkipped('SEARCH_LIVE_URL is not set.');
        }
        config(['services.search' => ['url' => $url, 'api_key' => '', 'username' => (string) getenv('SEARCH_LIVE_USERNAME'), 'password' => (string) getenv('SEARCH_LIVE_PASSWORD'), 'ca' => '', 'prefix' => 'ci_', 'timeout' => 30]]);
        $this->search = app(SearchClient::class);
    }

    protected function tearDown(): void
    {
        if (isset($this->search)) {
            foreach (array_keys(MarketIndices::all()) as $logical) {
                $this->search->deleteIndex($this->search->name($logical).'_v'.MarketIndices::VERSION);
            }
        }
        parent::tearDown();
    }

    public function test_the_indices_build_and_arabic_searches_find_what_a_customer_means(): void
    {
        $this->artisan('search:setup')->assertSuccessful();
        $this->artisan('search:setup')->assertSuccessful(); // again: nothing to do, alias kept
        $this->artisan('search:check')->assertSuccessful();

        $offers = $this->search->name(MarketIndices::OFFERS);
        $offer = fn (string $title, string $models, int $price) => ['tenant_id' => 't1', 'title' => $title, 'models' => $models, 'condition' => 'new', 'price' => $price, 'availability' => 'in', 'shop' => ['slug' => 'elnour', 'name' => 'النور', 'verified' => true], 'location' => ['lat' => 30.05, 'lon' => 31.24], 'governorate' => 'cairo', 'listed_at' => '2026-10-01T10:00:00Z'];
        $failed = $this->search->bulk($offers, [
            'case' => $offer('جراب سيليكون iPhone 15 Pro — أسود', 'iPhone 15 Pro', 15000),
            'glass' => $offer('لزقة شاشة 9D Galaxy A54', 'Galaxy A54', 7500),
            'charger' => $offer('شاحن سريع 25W سامسونج', 'Galaxy S24', 45000),
        ], refresh: true);
        $this->assertSame(0, $failed);

        $find = fn (string $q) => collect($this->search->search($offers, ['query' => ['multi_match' => [
            'query' => $q, 'fields' => ['title^3', 'models^2', 'title.suggest'], 'operator' => 'and',
        ]]])['hits']['hits'])->pluck('_id')->all();

        $this->assertSame(['case'], $find('ايفون 15 برو'));       // Arabic spelling + synonyms
        $this->assertSame(['case'], $find('جراب أيفون'));          // hamza folded
        $this->assertSame(['case'], $find('كفر iphone'));          // جراب = كفر
        $this->assertSame(['glass'], $find('a54'));
        $this->assertSame(['glass'], $find('اسكرينة a54'));        // اسكرينة = لزقة, ة folded
        $this->assertSame(['charger'], $find('شاحن samsung'));     // سامسونج = samsung

        // Unknown fields are refused (the mapping is strict), so a typo in the indexer shows at once.
        $this->assertSame(1, $this->search->bulk($offers, ['bad' => ['titel' => 'x']], refresh: true));
    }
}
