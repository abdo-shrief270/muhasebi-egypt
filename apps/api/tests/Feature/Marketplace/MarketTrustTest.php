<?php

namespace Tests\Feature\Marketplace;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\PermissionResolver;
use App\Modules\Marketplace\Contracts\PhoneCatalog;
use App\Modules\Marketplace\Support\MarketIndexer;
use App\Modules\Marketplace\Support\PhoneMatcher;
use App\Modules\OnlineStore\Contracts\ShopReputation;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** «سوق محاسبي» phase 4: reviews, unanswered orders, mistyped prices, reports, repair booking. */
class MarketTrustTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** @var list<array{method: string, path: string, body: string}> */
    private array $sent = [];

    private array $doc = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.store.host' => '', 'services.store.url' => 'https://store.muhasebi.com', 'services.market.answer_minutes' => 45]);
        config(['services.search' => ['url' => 'https://search.test', 'api_key' => 'K', 'username' => '', 'password' => '', 'ca' => '', 'prefix' => 'muhasebi_', 'timeout' => 5]]);
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $this->sent[] = ['method' => $request->method(), 'path' => $path, 'body' => $request->body()];

            return match (true) {
                str_ends_with($path, '/_search') => Http::response(['hits' => ['total' => ['value' => $this->doc ? 1 : 0], 'hits' => $this->doc ? [['_id' => 'v:b', '_source' => $this->doc]] : []], 'aggregations' => []]),
                str_ends_with($path, '/_bulk') => Http::response(['errors' => false, 'items' => []]),
                default => Http::response(['acknowledged' => true]),
            };
        });
        PhoneMatcher::forget();
        $this->openShopWithStock();
        app(PermissionResolver::class)->forget();
        $this->patchJson("/api/v1/branches/{$this->branchId}", ['governorate' => 'cairo', 'latitude' => 29.96, 'longitude' => 31.25])->assertOk();
        $this->putJson('/api/v1/online-store/settings', ['slug' => 'elnour', 'mode' => 'orders', 'pickup' => true])->assertOk();
    }

    private function order(string $phone, bool $market = true): array
    {
        return $this->postJson('/api/v1/public/stores/elnour/orders', [
            'name' => 'أحمد سمير', 'phone' => $phone, 'fulfilment' => 'pickup', 'payment' => 'cod',
            'items' => [['variant_id' => $this->v[0], 'qty' => 1]],
            ...($market ? ['branch_id' => $this->branchId] : []),
        ])->assertCreated()->json('data');
    }

    private function asAdmin(): void
    {
        Sanctum::actingAs(PlatformAdmin::firstOrCreate(['email' => 'admin@muhasebi.test'], ['name' => 'الإدارة', 'password' => 'secret-password']), ['admin']);
    }

    public function test_a_delivered_order_is_rated_once_and_admins_can_hide_a_review(): void
    {
        $order = $this->order('01055556666', market: false);
        $url = "/api/v1/public/stores/elnour/orders/{$order['token']}";
        $this->postJson("{$url}/review", ['rating' => 5])->assertStatus(409)->assertJsonPath('code', 'review_not_allowed');

        DB::table('online_orders')->where('token', $order['token'])->update(['status' => 'delivered']);
        $this->assertTrue($this->getJson($url)->json('data.can_review'));
        $this->postJson("{$url}/review", ['rating' => 6])->assertUnprocessable();
        $this->postJson("{$url}/review", ['rating' => 4, 'comment' => ' محل محترم '])->assertCreated()->assertJsonPath('data.name', 'أحمد');
        $this->postJson("{$url}/review", ['rating' => 1])->assertStatus(409)->assertJsonPath('code', 'already_reviewed');
        $track = $this->getJson($url)->json('data');
        $this->assertSame([false, 4, 'محل محترم'], [$track['can_review'], $track['review']['rating'], $track['review']['comment']]);

        $tenant = $this->owner->tenant_id;
        $this->assertSame(['rating' => 4.0, 'reviews' => 1, 'response' => null], app(ShopReputation::class)->scores([$tenant])[$tenant]);
        $doc = array_values(array_filter(app(MarketIndexer::class)->documents($tenant)))[0];
        $this->assertSame([4.0, 1], [$doc['shop']['rating'], $doc['shop']['reviews']]);

        $this->asAdmin();
        $review = $this->getJson('/api/v1/admin/market/reviews')->assertOk()->json('data.0');
        $this->assertSame(4, $review['rating']);
        $this->assertNotNull($review['shop']);
        $this->patchJson("/api/v1/admin/market/reviews/{$review['id']}", ['hidden' => true])->assertOk();
        $this->assertSame(0, app(ShopReputation::class)->scores([$tenant])[$tenant]['reviews'] ?? 0);
    }

    public function test_unanswered_marketplace_orders_are_cancelled_and_count_against_the_shop(): void
    {
        $answered = $this->order('01055550001');
        $late = [$this->order('01055550002'), $this->order('01055550003')];
        $storeOrder = $this->order('01055550004', market: false);
        $id = DB::table('online_orders')->where('token', $answered['token'])->value('id');
        $this->postJson("/api/v1/online-store/orders/{$id}/status", ['status' => 'confirmed'])->assertOk();

        $this->travel(46)->minutes();
        $this->artisan('online-store:expire-unanswered')->assertSuccessful();

        $statuses = DB::table('online_orders')->pluck('status', 'token');
        $this->assertSame(['confirmed', 'cancelled', 'cancelled', 'new'], [$statuses[$answered['token']], $statuses[$late[0]['token']], $statuses[$late[1]['token']], $statuses[$storeOrder['token']]]);
        $track = $this->getJson("/api/v1/public/stores/elnour/orders/{$late[0]['token']}")->json('data');
        $this->assertTrue($track['auto_cancelled']);
        $this->assertStringContainsString('ما ردّش', $track['cancel_reason']);

        $tenant = $this->owner->tenant_id;
        $this->assertSame(0.33, app(ShopReputation::class)->scores([$tenant])[$tenant]['response']);

        // Search lets late-answering shops sink and never lists held-back offers.
        $this->getJson('/api/v1/public/market/search?q=جراب')->assertOk();
        $body = collect($this->sent)->last()['body'];
        $this->assertStringContainsString('"shop.response":{"lt":0.5}', $body);
        $this->assertStringContainsString('"must_not":[{"exists":{"field":"flag"}}]', $body);
    }

    public function test_a_phone_priced_far_from_the_others_waits_for_the_shop(): void
    {
        $pro = app(PhoneCatalog::class)->findBySlug('apple-iphone-15-pro');
        DB::table('market_price_refs')->insert(['item_id' => $pro['id'], 'median' => 5_500_000, 'shops' => 4, 'updated_at' => now()]);
        $phones = $this->inShop(fn () => Category::query()->where('name', 'موبايلات جديدة')->value('id'));
        $variant = $this->postJson('/api/v1/products', ['name' => 'ايفون 15 برو 256', 'category_id' => $phones, 'variants' => [['price_retail' => 550_000]]])->assertCreated()->json('data.variants.0.id');
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $variant, 'qty' => 1, 'unit_cost' => 400_000]]])->assertCreated();

        $key = "{$variant}:{$this->branchId}";
        $tenant = $this->owner->tenant_id;
        $this->assertSame('price', app(MarketIndexer::class)->documents($tenant, [$variant])[$key]['flag']);
        $held = $this->getJson('/api/v1/marketplace/held-prices')->assertOk()->json('data');
        $this->assertSame([[$variant, 550_000, 5_500_000]], array_map(fn ($h) => [$h['variant_id'], $h['price'], $h['median']], $held));

        $this->postJson("/api/v1/marketplace/held-prices/{$variant}/confirm")->assertOk();
        $this->assertNull(app(MarketIndexer::class)->documents($tenant, [$variant])[$key]['flag']);
        $this->assertSame([], $this->getJson('/api/v1/marketplace/held-prices')->json('data'));

        // A price in line with the others is never held.
        DB::table('market_price_confirmations')->delete();
        DB::table('product_variants')->where('id', $variant)->update(['price_retail' => 5_400_000]);
        $this->assertNull(app(MarketIndexer::class)->documents($tenant, [$variant])[$key]['flag']);
    }

    public function test_a_report_reaches_the_admins_who_can_hide_the_offer(): void
    {
        $key = "{$this->v[0]}:{$this->branchId}";
        $this->doc = ['tenant_id' => $this->owner->tenant_id, 'variant_id' => $this->v[0], 'title' => 'جراب'];
        $this->postJson('/api/v1/public/market/reports', ['offer_id' => $key, 'reason' => 'wrong_price', 'note' => 'السعر في المحل أغلى'])->assertCreated();
        $this->postJson('/api/v1/public/market/reports', ['offer_id' => $key, 'reason' => 'wrong_price'])->assertCreated();
        $this->postJson('/api/v1/public/market/reports', ['offer_id' => $key, 'reason' => 'nope'])->assertUnprocessable();
        $this->postJson('/api/v1/public/market/reports', ['offer_id' => $key, 'reason' => 'fake', 'website' => 'x'])->assertUnprocessable();
        $this->assertSame(1, DB::table('market_reports')->count(), 'the same visitor reports an offer once a day');

        $this->asAdmin();
        $this->assertSame(1, $this->getJson('/api/v1/admin/badges')->json('data.market'));
        $report = $this->getJson('/api/v1/admin/market/reports')->assertOk()->json('data.0');
        $this->assertSame(['جراب', 'السعر غلط', $this->owner->tenant_id], [$report['title'], $report['reason_label'], $report['tenant_id']]);
        $this->postJson("/api/v1/admin/market/reports/{$report['id']}/resolve", ['action' => 'hide'])->assertOk();
        $this->assertSame([], $this->getJson('/api/v1/admin/market/reports')->json('data'));
        $this->assertDatabaseHas('platform_admin_actions', ['action' => 'market_offer_hidden']);

        $tenant = $this->owner->tenant_id;
        $this->assertNull(app(MarketIndexer::class)->documents($tenant, [$this->v[0]])[$key]);
        $this->postJson('/api/v1/admin/market/unblock', ['tenant_id' => $tenant, 'variant_id' => $this->v[0]])->assertOk();
        $this->assertNotNull(app(MarketIndexer::class)->documents($tenant, [$this->v[0]])[$key]);
    }

    public function test_repairs_are_booked_from_the_market_at_shops_that_take_them(): void
    {
        $this->assertSame([], $this->getJson('/api/v1/public/market/repair-shops')->assertOk()->json('data'));
        $this->putJson('/api/v1/online-store/settings', ['repair_booking' => true, 'repair_booking_note' => 'الكشف ببلاش'])->assertOk();
        Cache::forget('market:repair-shops:v1');

        $shops = $this->getJson('/api/v1/public/market/repair-shops?lat=30.0&lng=31.2')->assertOk()->json('data');
        $this->assertSame(['elnour', 'الكشف ببلاش', 'cairo'], [$shops[0]['store'], $shops[0]['note'], $shops[0]['governorate']]);
        $this->assertNotNull($shops[0]['distance_km']);
        $this->assertSame([], $this->getJson('/api/v1/public/market/repair-shops?governorate=giza')->json('data'));

        $this->postJson('/api/v1/public/stores/elnour/repair-bookings', [
            'name' => 'منى', 'phone' => '01066667777', 'device' => 'iPhone 13', 'problem' => 'الشاشة مكسورة', 'source' => 'market',
        ])->assertCreated();
        $this->assertSame('market', $this->getJson('/api/v1/online-store/bookings')->assertOk()->json('data.0.source'));
    }
}
