<?php

namespace Tests\Feature;

use App\Support\Search\MarketIndices;
use App\Support\Search\SearchClient;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

/** The marketplace's search client and its setup commands (no cluster needed: HTTP is faked). */
class SearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config(['services.search' => ['url' => 'https://search.test:9200', 'api_key' => 'KEY', 'username' => '', 'password' => '', 'ca' => '', 'prefix' => 'muhasebi_', 'timeout' => 5]]);
    }

    public function test_setup_creates_the_versioned_indices_and_points_the_aliases_at_them(): void
    {
        $created = [];
        Http::fake(function (Request $request) use (&$created) {
            $path = parse_url($request->url(), PHP_URL_PATH);
            $this->assertSame('ApiKey KEY', $request->header('Authorization')[0]);

            return match (true) {
                $request->method() === 'PUT' => (function () use (&$created, $path, $request) {
                    $created[ltrim($path, '/')] = $request->data();

                    return Http::response(['acknowledged' => true]);
                })(),
                $path === '/_alias/muhasebi_market_offers' => Http::response(['muhasebi_market_offers_v0' => ['aliases' => []]]),
                str_starts_with($path, '/_alias/') => Http::response(['error' => 'missing'], 404),
                default => Http::response(['acknowledged' => true]),
            };
        });

        $this->artisan('search:setup')->assertSuccessful();

        $v = MarketIndices::VERSION;
        $this->assertCount(2, $created);
        [$offersIndex, $itemsIndex] = array_keys($created);
        $this->assertMatchesRegularExpression("/^muhasebi_market_offers_v{$v}_\\d{14}$/", $offersIndex);
        $this->assertMatchesRegularExpression("/^muhasebi_market_items_v{$v}_\\d{14}$/", $itemsIndex);
        $offers = $created[$offersIndex];
        $this->assertSame('strict', $offers['mappings']['dynamic']);
        $this->assertContains('iphone, ايفون, ايفن, اي فون', $offers['settings']['analysis']['filter']['ar_synonyms']['synonyms']);

        Http::assertSent(fn (Request $r) => str_ends_with($r->url(), '/_aliases') && $r->data()['actions'] === [
            ['remove' => ['index' => 'muhasebi_market_offers_v0', 'alias' => 'muhasebi_market_offers']],
            ['add' => ['index' => $offersIndex, 'alias' => 'muhasebi_market_offers']],
        ]);
        // The previous version's index goes once the alias has moved.
        Http::assertSent(fn (Request $r) => $r->method() === 'DELETE' && str_ends_with($r->url(), '/muhasebi_market_offers_v0'));
    }

    public function test_setup_keeps_an_index_of_this_version_even_a_nightly_rebuild(): void
    {
        $v = MarketIndices::VERSION;
        Http::fake(fn (Request $r) => str_starts_with((string) parse_url($r->url(), PHP_URL_PATH), '/_alias/')
            ? Http::response([str_replace('/_alias/', '', (string) parse_url($r->url(), PHP_URL_PATH))."_v{$v}_20261101041100" => ['aliases' => []]])
            : Http::response(['acknowledged' => true]));

        $this->artisan('search:setup')->assertSuccessful();

        Http::assertNotSent(fn (Request $r) => in_array($r->method(), ['PUT', 'DELETE'], true) || str_ends_with($r->url(), '/_aliases'));
    }

    public function test_bulk_sends_ndjson_and_counts_real_failures_only(): void
    {
        Http::fake(['search.test:9200/_bulk*' => Http::response(['errors' => true, 'items' => [
            ['index' => ['status' => 201]],
            ['delete' => ['status' => 404]],
            ['index' => ['status' => 400, 'error' => ['type' => 'mapper_parsing_exception']]],
        ]])]);

        $failed = app(SearchClient::class)->bulk('muhasebi_market_offers', ['a' => ['title' => 'جراب'], 'b' => null, 'c' => ['title' => 'x']], refresh: true);

        $this->assertSame(1, $failed);
        Http::assertSent(function (Request $r) {
            $lines = explode("\n", trim($r->body()));

            return str_ends_with($r->url(), '/_bulk?refresh=wait_for')
                && $r->header('Content-Type')[0] === 'application/x-ndjson'
                && count($lines) === 5
                && json_decode($lines[1], true) === ['title' => 'جراب']
                && json_decode($lines[2], true) === ['delete' => ['_index' => 'muhasebi_market_offers', '_id' => 'b']];
        });
    }

    public function test_errors_carry_the_reason_and_nothing_runs_without_a_url(): void
    {
        Http::fake(['*' => Http::response(['error' => ['reason' => 'action [indices:data/read/search] is unauthorized']], 403)]);
        try {
            app(SearchClient::class)->search('muhasebi_market_offers', ['query' => ['match_all' => (object) []]]);
            $this->fail('expected an exception');
        } catch (RuntimeException $e) {
            $this->assertStringContainsString('(403): action [indices:data/read/search] is unauthorized', $e->getMessage());
        }

        config(['services.search.url' => '']);
        $this->assertFalse(app(SearchClient::class)->enabled());
        $this->artisan('search:setup')->assertFailed();
        $this->artisan('search:check')->assertFailed();
    }

    public function test_check_prints_the_cluster_and_the_analyzed_tokens(): void
    {
        Http::fake([
            'search.test:9200/_cluster/health' => Http::response(['status' => 'green']),
            'search.test:9200/_alias/*' => Http::response(['muhasebi_market_offers_v1' => ['aliases' => []]]),
            'search.test:9200/muhasebi_market_offers/_analyze' => Http::response(['tokens' => [['token' => 'جراب'], ['token' => 'iphone']]]),
            'search.test:9200/' => Http::response(['cluster_name' => 'muhasebi', 'version' => ['number' => '9.1.0']]),
        ]);

        $this->artisan('search:check')
            ->expectsOutputToContain('Elasticsearch 9.1.0 — green')
            ->expectsOutputToContain('جراب · iphone')
            ->assertSuccessful();
    }
}
