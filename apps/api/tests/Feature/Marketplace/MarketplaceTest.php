<?php

namespace Tests\Feature\Marketplace;

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\PermissionResolver;
use App\Modules\Marketplace\Support\MarketIndexer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** «سوق محاسبي»: what a shop offers reaches the search index (faked), its settings, and the public search. */
class MarketplaceTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** @var list<array{method: string, path: string, body: string}> */
    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.search' => ['url' => 'https://search.test', 'api_key' => 'K', 'username' => '', 'password' => '', 'ca' => '', 'prefix' => 'muhasebi_', 'timeout' => 5]]);
        $this->fakeCluster();
        $this->openShopWithStock();
        app(PermissionResolver::class)->forget();
        $this->patchJson("/api/v1/branches/{$this->branchId}", ['governorate' => 'cairo', 'area' => 'المعادي', 'latitude' => 29.96, 'longitude' => 31.25])->assertOk();
    }

    public function test_a_branch_records_where_it_is(): void
    {
        $this->patchJson("/api/v1/branches/{$this->branchId}", ['governorate' => 'atlantis'])->assertUnprocessable()->assertJsonValidationErrors('governorate');
        // A point outside Egypt is a typo.
        $this->patchJson("/api/v1/branches/{$this->branchId}", ['latitude' => 48.85, 'longitude' => 2.35])->assertUnprocessable()->assertJsonValidationErrors(['latitude', 'longitude']);
        $this->getJson('/api/v1/branches')->assertOk()
            ->assertJsonPath('data.0.governorate', 'cairo')
            ->assertJsonPath('data.0.area', 'المعادي')
            ->assertJsonPath('data.0.latitude', 29.96);
    }

    public function test_offers_follow_the_catalog_stock_branches_and_settings(): void
    {
        $indexer = app(MarketIndexer::class);
        $tenant = $this->owner->tenant_id;

        $docs = array_filter($indexer->documents($tenant));
        $this->assertCount(2, $docs);
        $case = $docs["{$this->v[0]}:{$this->branchId}"];
        $this->assertSame(['جراب', 10000, 'in', 'new', 'cairo', 'المعادي'], [$case['title'], $case['price'], $case['availability'], $case['condition'], $case['governorate'], $case['area']]);
        $this->assertSame(['lat' => 29.96, 'lon' => 31.25], $case['location']);
        $this->assertSame($tenant, $case['tenant_id']);
        $this->assertArrayNotHasKey('cost', $case);

        // Sold down to 2: «قرب يخلص»; sold out: it leaves the index.
        $this->postJson('/api/v1/inventory/adjustments', ['reason' => 'count', 'items' => [['variant_id' => $this->v[1], 'counted' => 2]]])->assertSuccessful();
        $this->assertSame('low', $indexer->documents($tenant, [$this->v[1]])["{$this->v[1]}:{$this->branchId}"]['availability']);
        $this->postJson('/api/v1/inventory/adjustments', ['reason' => 'count', 'items' => [['variant_id' => $this->v[1], 'counted' => 0]]])->assertSuccessful();
        $this->assertSame(["{$this->v[1]}:{$this->branchId}" => null], $indexer->documents($tenant, [$this->v[1]]));

        // A hidden category's products leave (and a reindex of the shop is queued).
        $cases = $this->inShop(fn () => Category::query()->where('name', 'جرابات')->value('id'));
        $this->putJson('/api/v1/marketplace/settings', ['hidden_categories' => [$cases]])->assertOk()->assertJsonPath('data.hidden_categories', [$cases]);
        $this->assertSame(["{$this->v[0]}:{$this->branchId}" => null], $indexer->documents($tenant, [$this->v[0]]));
        $this->assertTrue(collect($this->sent)->contains(fn ($r) => str_contains($r['path'], '_delete_by_query')));

        // A hidden branch: nothing from it.
        $this->putJson('/api/v1/marketplace/settings', ['hidden_categories' => [], 'hidden_branches' => [$this->branchId]])->assertOk();
        $this->assertSame([], array_filter($indexer->documents($tenant)));
    }

    public function test_a_shop_that_hides_itself_is_not_synced(): void
    {
        $this->putJson('/api/v1/marketplace/settings', ['listed' => false])->assertOk()->assertJsonPath('data.listed', false);
        $this->sent = [];
        $this->assertSame(0, app(MarketIndexer::class)->syncVariants($this->owner->tenant_id, [$this->v[0]]));
        $this->assertSame([], $this->sent);
        $this->assertFalse(app(MarketIndexer::class)->listed($this->owner->tenant_id));
    }

    public function test_sync_sends_what_changed_since_the_last_run(): void
    {
        DB::table('market_sync_marks')->insert(['name' => 'offers', 'at' => now()->subMinute()]);
        $this->travel(10)->seconds(); // past the few seconds of overlap each run re-reads
        $this->sent = [];
        $this->artisan('market:sync')->assertSuccessful();

        $bulk = collect($this->sent)->firstWhere(fn ($r) => str_starts_with($r['path'], '/_bulk'));
        $this->assertNotNull($bulk);
        $this->assertStringContainsString("{$this->v[0]}:{$this->branchId}", $bulk['body']);
        $this->assertStringContainsString("{$this->v[1]}:{$this->branchId}", $bulk['body']);

        // Nothing changed since: nothing sent.
        $this->travel(1)->minutes();
        $this->sent = [];
        $this->artisan('market:sync')->assertSuccessful();
        $this->assertFalse(collect($this->sent)->contains(fn ($r) => str_starts_with($r['path'], '/_bulk')));
    }

    public function test_the_nightly_rebuild_fills_a_new_index_then_moves_the_alias(): void
    {
        $this->artisan('market:reindex')->assertSuccessful();

        $paths = array_map(fn ($r) => "{$r['method']} {$r['path']}", $this->sent);
        $created = collect($paths)->first(fn ($p) => preg_match('#^PUT /muhasebi_market_offers_v\d+_\d{14}$#', $p));
        $this->assertNotNull($created);
        $index = substr($created, 5);
        $this->assertContains('POST /_bulk', $paths);
        $this->assertStringContainsString($index, collect($this->sent)->firstWhere('path', '/_bulk')['body']);
        $aliases = collect($this->sent)->firstWhere('path', '/_aliases');
        $this->assertStringContainsString($index, $aliases['body']);
        $this->assertContains('DELETE /muhasebi_market_offers_v1_20260101000000', $paths);
    }

    public function test_settings_show_the_status_and_refuse_other_shops_ids(): void
    {
        $data = $this->getJson('/api/v1/marketplace/settings')->assertOk()->json('data');
        $this->assertTrue($data['listed']);
        $this->assertSame(['trialing', true, true], [$data['status']['subscription'], $data['status']['subscription_ok'], $data['status']['search']]);
        $this->assertSame(7, $data['status']['offers']);
        $this->assertSame([true, 'القاهرة'], [$data['branches'][0]['located'], $data['branches'][0]['governorate_label']]);

        $this->putJson('/api/v1/marketplace/settings', ['hidden_branches' => ['00000000-0000-0000-0000-000000000000']])->assertUnprocessable();
        $this->putJson('/api/v1/marketplace/settings', ['hidden_products' => ['00000000-0000-0000-0000-000000000000']])
            ->assertOk()->assertJsonPath('data.hidden_products', []);

        $cashier = $this->staff('cashier');
        $this->actingAs($cashier)->getJson('/api/v1/marketplace/settings')->assertForbidden();
    }

    public function test_public_search_builds_the_query_and_maps_the_hits(): void
    {
        $data = $this->getJson('/api/v1/public/market/search?q=جراب ايفون&governorate=cairo&condition=new&min=5000&sort=price_asc')
            ->assertOk()->assertHeader('Cache-Control', 'max-age=30, public')->json('data');

        $search = collect($this->sent)->firstWhere('path', '/muhasebi_market_offers/_search');
        $body = json_decode($search['body'], true);
        $filters = $body['query']['function_score']['query']['bool']['filter'];
        $this->assertContains(['term' => ['governorate' => 'cairo']], $filters);
        $this->assertContains(['term' => ['condition' => 'new']], $filters);
        $this->assertContains(['range' => ['price' => ['gte' => 5000]]], $filters);
        $this->assertSame([['price' => 'asc'], '_score'], $body['sort']);
        $this->assertSame(['field' => 'variant_id'], $body['collapse']);

        $this->assertSame(1, $data['total']);
        $this->assertSame(['جراب سيليكون', 15000, 'elnour', 'القاهرة'], [$data['items'][0]['title'], $data['items'][0]['price'], $data['items'][0]['shop']['slug'], $data['items'][0]['place']['governorate_label']]);
        $this->assertSame([['key' => 'accessory', 'label' => 'إكسسوارات', 'count' => 1]], $data['facets']['category']);

        $this->getJson('/api/v1/public/market/search?governorate=atlantis')->assertUnprocessable();
        $this->assertSame('cairo', $this->getJson('/api/v1/public/market/options')->assertOk()->json('data.governorates.0.key'));

        config(['services.search.url' => '']);
        $this->getJson('/api/v1/public/market/search?q=x')->assertStatus(503)->assertJsonPath('code', 'market_unavailable');
    }

    /** A cluster that answers everything, records what it was sent, and returns one hit to searches. */
    private function fakeCluster(): void
    {
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $this->sent[] = ['method' => $request->method(), 'path' => $path, 'body' => $request->body()];

            return match (true) {
                str_starts_with($path, '/_alias/') => Http::response(['muhasebi_market_offers_v1_20260101000000' => ['aliases' => []]]),
                str_ends_with($path, '/_count') => Http::response(['count' => 7]),
                str_ends_with($path, '/_bulk') => Http::response(['errors' => false, 'items' => []]),
                str_ends_with($path, '/_delete_by_query') => Http::response(['deleted' => 2]),
                str_ends_with($path, '/_search') => Http::response([
                    'hits' => ['total' => ['value' => 1], 'hits' => [[
                        '_id' => 'v1:b1',
                        '_source' => ['variant_id' => 'v1', 'product_id' => 'p1', 'title' => 'جراب سيليكون', 'brand' => null, 'category' => 'accessory', 'condition' => 'new', 'grade' => null, 'price' => 15000, 'availability' => 'in', 'image' => null, 'shop' => ['slug' => 'elnour', 'name' => 'النور'], 'governorate' => 'cairo', 'area' => 'المعادي'],
                        'sort' => [15000, 1.2],
                    ]]],
                    'aggregations' => [
                        'category' => ['buckets' => [['key' => 'accessory', 'doc_count' => 1]]],
                        'price_min' => ['value' => 15000], 'price_max' => ['value' => 15000],
                    ],
                ]),
                default => Http::response(['acknowledged' => true]),
            };
        });
    }
}
