<?php

namespace Tests\Feature\Marketplace;

use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\DeviceModel;
use App\Modules\Identity\PermissionResolver;
use App\Modules\Marketplace\Contracts\PhoneCatalog;
use App\Modules\Marketplace\Support\MarketIndexer;
use App\Modules\Marketplace\Support\PhoneMatcher;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** A shop's phones join the reference catalog: matched from their names, or linked by the shop. */
class PhoneLinksTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** @var list<array{method: string, path: string, body: string}> */
    private array $sent = [];

    private array $searchResponse = [];

    protected function setUp(): void
    {
        parent::setUp();
        config(['services.search' => ['url' => 'https://search.test', 'api_key' => 'K', 'username' => '', 'password' => '', 'ca' => '', 'prefix' => 'muhasebi_', 'timeout' => 5]]);
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $this->sent[] = ['method' => $request->method(), 'path' => $path, 'body' => $request->body()];

            return match (true) {
                str_starts_with($path, '/_alias/') => Http::response(['muhasebi_market_offers_v1_20260101000000' => ['aliases' => []]]),
                str_ends_with($path, '/_bulk') => Http::response(['errors' => false, 'items' => []]),
                str_ends_with($path, '/_search') => Http::response($this->searchResponse ?: ['hits' => ['total' => ['value' => 0], 'hits' => []], 'aggregations' => []]),
                default => Http::response(['acknowledged' => true]),
            };
        });
        PhoneMatcher::forget();
        $this->openShopWithStock();
        app(PermissionResolver::class)->forget();
        $this->patchJson("/api/v1/branches/{$this->branchId}", ['governorate' => 'cairo', 'latitude' => 29.96, 'longitude' => 31.25])->assertOk();
    }

    public function test_the_matcher_reads_names_like_a_person(): void
    {
        $m = app(PhoneMatcher::class);
        $cases = [
            ['ايفون 15 برو ماكس 256 جيجا', null, 'apple-iphone-15-pro-max', 256],
            ['iphone15 128gb', null, 'apple-iphone-15', 128],
            ['Samsung A55 8/256', 'Samsung', 'samsung-galaxy-a55-5g', 256],
            ['Redmi Note 13 Pro 5G 256GB', 'Xiaomi', 'xiaomi-redmi-note-13-pro-5g', 256],
            ['Redmi Note 13', 'Redmi', 'xiaomi-redmi-note-13', null],
            ['ريلمي سي 67', null, 'realme-realme-c67', null],
            ['Galaxy S24 Ultra 512', null, 'samsung-galaxy-s24-ultra', 512],
        ];
        foreach ($cases as [$name, $brand, $slug, $storage]) {
            $found = $m->match($name, $brand);
            $this->assertSame([$slug, $storage], [$found['phone']['slug'] ?? null, $found['storage_gb'] ?? null], $name);
        }
        // A bigger model than any in the list, another maker's «A55», a case: no match.
        $this->assertNull($m->match('iPhone 15 Ultra'));
        $this->assertNull($m->match('Oppo A55 Galaxy'));
        $this->assertNull($m->match('جراب سيليكون'));
    }

    public function test_phones_are_matched_linked_by_the_shop_and_indexed(): void
    {
        $phones = $this->inShop(fn () => Category::query()->where('name', 'موبايلات جديدة')->value('id'));
        $iphone = $this->postJson('/api/v1/products', ['name' => 'ايفون 15 برو', 'category_id' => $phones, 'variants' => [['name' => '256 جيجا', 'price_retail' => 5500000]]])->assertCreated()->json('data.variants.0.id');
        $odd = $this->postJson('/api/v1/products', ['name' => 'موبايل صيني', 'category_id' => $phones, 'variants' => [['price_retail' => 300000]]])->assertCreated()->json('data.variants.0.id');
        // A case that fits the iPhone 15 Pro (one of the shop's compatible models).
        $model = $this->inShop(fn () => DeviceModel::query()->where('name', 'iPhone 15 Pro')->value('id'));
        $cases = $this->inShop(fn () => Category::query()->where('name', 'جرابات')->value('id'));
        $case = $this->postJson('/api/v1/products', ['name' => 'جراب شفاف', 'category_id' => $cases, 'device_model_ids' => [$model], 'variants' => [['price_retail' => 15000]]])->assertCreated()->json('data.variants.0.id');
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $iphone, 'qty' => 2, 'unit_cost' => 5000000], ['variant_id' => $odd, 'qty' => 1, 'unit_cost' => 200000], ['variant_id' => $case, 'qty' => 5, 'unit_cost' => 5000]]])->assertCreated();

        $list = $this->getJson('/api/v1/marketplace/phones')->assertOk()->json('data');
        $this->assertSame(['auto' => 1, 'shop' => 0, 'unmatched' => 1, 'excluded' => 0], $list['counts']);
        $this->assertSame([$odd, 'unmatched'], [$list['items'][0]['variant_id'], $list['items'][0]['state']]);
        $this->assertSame(['apple-iphone-15-pro', 256], [$list['items'][1]['phone']['slug'], $list['items'][1]['storage_gb']]);

        $tenant = $this->owner->tenant_id;
        $docs = app(MarketIndexer::class)->documents($tenant);
        $pro = app(PhoneCatalog::class)->findBySlug('apple-iphone-15-pro');
        $this->assertSame([$pro['id'], 256], [$docs["{$iphone}:{$this->branchId}"]['item_id'], $docs["{$iphone}:{$this->branchId}"]['storage_gb']]);
        $this->assertNull($docs["{$odd}:{$this->branchId}"]['item_id']);
        $this->assertSame([$pro['id']], $docs["{$case}:{$this->branchId}"]['fits']);

        // The shop links the odd one by hand (picked from the catalog) and says the iPhone isn't one.
        $a16 = $this->getJson('/api/v1/marketplace/phones/catalog?q=Galaxy A16')->assertOk()->json('data.0');
        $this->sent = [];
        $this->putJson("/api/v1/marketplace/phones/{$odd}", ['phone_id' => $a16['id'], 'storage_gb' => 128])->assertOk();
        $this->assertTrue(collect($this->sent)->contains(fn ($r) => $r['path'] === '/_bulk' && str_contains($r['body'], $a16['id'])));
        $this->putJson("/api/v1/marketplace/phones/{$iphone}", ['phone_id' => null])->assertOk();
        $this->putJson("/api/v1/marketplace/phones/{$case}", ['phone_id' => null])->assertNotFound(); // a case isn't a phone

        $counts = $this->getJson('/api/v1/marketplace/phones')->json('data.counts');
        $this->assertSame(['auto' => 0, 'shop' => 1, 'unmatched' => 0, 'excluded' => 1], $counts);
        $docs = app(MarketIndexer::class)->documents($tenant, [$iphone, $odd]);
        $this->assertNull($docs["{$iphone}:{$this->branchId}"]['item_id']);
        $this->assertSame([$a16['id'], 128], [$docs["{$odd}:{$this->branchId}"]['item_id'], $docs["{$odd}:{$this->branchId}"]['storage_gb']]);
        $this->assertDatabaseHas('audit_log', ['action' => 'marketplace.phone_link']);

        // Back to automatic, then confirmed in one go.
        $this->deleteJson("/api/v1/marketplace/phones/{$iphone}")->assertOk();
        $this->postJson('/api/v1/marketplace/phones/confirm', ['variant_ids' => [$iphone, $odd]])->assertOk()->assertJsonPath('data.confirmed', 1);
        $this->assertSame(['auto' => 0, 'shop' => 2, 'unmatched' => 0, 'excluded' => 0], $this->getJson('/api/v1/marketplace/phones')->json('data.counts'));

        $this->putJson("/api/v1/marketplace/phones/{$odd}", ['phone_id' => '01a12c73-bf68-7018-8b85-4c765ed709d1'])->assertUnprocessable()->assertJsonPath('code', 'phone_not_found');
    }

    public function test_public_phone_prices_and_page(): void
    {
        $pro = app(PhoneCatalog::class)->findBySlug('apple-iphone-15-pro');
        $this->searchResponse = [
            'hits' => ['total' => ['value' => 1], 'hits' => [[
                '_id' => 'v1:b1',
                '_source' => ['variant_id' => 'v1', 'product_id' => 'p1', 'item_id' => $pro['id'], 'storage_gb' => 256, 'title' => 'ايفون 15 برو', 'condition' => 'new', 'price' => 5500000, 'shop' => ['slug' => 'elnour', 'name' => 'النور'], 'governorate' => 'cairo'],
            ]]],
            'aggregations' => ['phones' => ['buckets' => [[
                'key' => $pro['id'], 'doc_count' => 3,
                'condition' => ['buckets' => [['key' => 'new', 'min' => ['value' => 5400000], 'max' => ['value' => 5600000], 'offers' => ['value' => 3], 'shops' => ['value' => 2]]]],
                'offers' => ['value' => 4],
            ]]]],
        ];

        $list = $this->getJson('/api/v1/public/market/phones?brand=apple')->assertOk()->json('data');
        $row = collect($list['phones'])->firstWhere('slug', 'apple-iphone-15-pro');
        $this->assertSame(['min' => 5400000, 'max' => 5600000, 'offers' => 3, 'shops' => 2], $row['prices']['new']);
        $this->assertTrue(collect($list['phones'])->every(fn ($p) => $p['brand'] === 'Apple'));

        $page = $this->getJson('/api/v1/public/market/phones/apple-iphone-15-pro')->assertOk()->json('data');
        $this->assertSame(['Apple iPhone 15 Pro', 'v1:b1', 256], [$page['phone']['name'], $page['new'][0]['id'], $page['new'][0]['storage_gb']]);
        $this->assertTrue(collect($this->sent)->contains(fn ($r) => str_contains($r['body'], '"item_id":"'.$pro['id'].'"')));
        $this->getJson('/api/v1/public/market/phones/nokia-3310-x')->assertNotFound();

        // Search filters by the phone it is / the phone it fits.
        $this->sent = [];
        $this->getJson('/api/v1/public/market/search?fits=apple-iphone-15-pro')->assertOk();
        $this->assertStringContainsString('"fits":"'.$pro['id'].'"', collect($this->sent)->last()['body']);

        // The offer page knows its phone.
        $this->getJson('/api/v1/public/market/offers/v1:b1')->assertOk()->assertJsonPath('data.phone.slug', 'apple-iphone-15-pro');
    }

    public function test_a_new_index_definition_is_rebuilt_on_deploy(): void
    {
        $this->artisan('market:reindex --if-outdated')->assertSuccessful();
        $this->assertTrue(collect($this->sent)->contains(fn ($r) => $r['path'] === '/_aliases'));
    }
}
