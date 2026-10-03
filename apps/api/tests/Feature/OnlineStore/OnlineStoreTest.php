<?php

namespace Tests\Feature\OnlineStore;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** «المتجر الأونلاين»: the owner's settings and the public, read-only store. */
class OnlineStoreTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        // Stores at a shared address unless a test says otherwise (never from the machine's .env).
        config(['services.store.host' => '', 'services.store.url' => 'https://store.muhasebi.com']);
        Storage::fake('local');
        $this->openShopWithStock();
        app(PermissionResolver::class)->forget();
    }

    private function open(array $settings = []): array
    {
        return $this->putJson('/api/v1/online-store/settings', ['slug' => 'elnour', 'mode' => 'whatsapp', ...$settings])->assertOk()->json('data');
    }

    public function test_the_store_starts_closed_from_the_shop_profile(): void
    {
        $store = $this->getJson('/api/v1/online-store/settings')->assertOk()->json('data');
        $this->assertSame(['off', 'محل 1', $this->branchId, '+201000000001'], [$store['mode'], $store['name'], $store['branch_id'], $store['whatsapp']]);
        $this->getJson("/api/v1/public/stores/{$store['slug']}")->assertNotFound()->assertJsonPath('code', 'store_not_found');

        foreach (['ab', 'Admin', 'store', 'with space', '-x-', 'a--b'] as $bad) {
            $this->putJson('/api/v1/online-store/settings', ['slug' => $bad])->assertUnprocessable()->assertJsonPath('code', 'slug_invalid');
        }
        $this->putJson('/api/v1/online-store/settings', ['mode' => 'whatsapp', 'whatsapp' => null])->assertUnprocessable()->assertJsonPath('code', 'whatsapp_required');
        $open = $this->open(['whatsapp' => '01012345678', 'color' => '#112233']);
        $this->assertSame(['elnour', 'whatsapp', '+201012345678', '#112233'], [$open['slug'], $open['mode'], $open['whatsapp'], $open['color']]);
        $this->assertStringEndsWith('/elnour', $open['url']);

        // Another shop can't take the address.
        $other = $this->registerShop(ShopType::Accessories);
        Sanctum::actingAs($other);
        app(PermissionResolver::class)->forget();
        $this->putJson('/api/v1/online-store/settings', ['slug' => 'elnour'])->assertUnprocessable()->assertJsonPath('code', 'slug_taken');
    }

    public function test_the_public_store_shows_products_by_category_model_and_search(): void
    {
        $productId = $this->getJson('/api/v1/products?q=جراب')->json('data.0.id');
        $brand = $this->postJson('/api/v1/catalog/brands', ['name' => 'Zeta'])->assertCreated()->json('data.id');
        $model = $this->postJson("/api/v1/catalog/brands/{$brand}/models", ['name' => 'Z1'])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/products/{$productId}", ['device_model_ids' => [$model], 'online_description' => 'جراب سيليكون'])->assertOk();
        $this->open();

        $home = $this->getJson('/api/v1/public/stores/elnour')->assertOk()->assertHeader('Cache-Control', 'max-age=30, public')->json('data');
        $this->assertSame('elnour', $home['store']['slug']);
        $this->assertEqualsCanonicalizing(['جرابات', 'شواحن'], array_column($home['categories'], 'name'));
        $this->assertSame([['id' => $model, 'name' => 'Z1', 'products' => 1]], $home['device_brands'][0]['models']);
        $this->assertCount(2, $home['latest']);

        $byModel = $this->getJson("/api/v1/public/stores/elnour/products?model={$model}")->assertOk();
        $this->assertSame(['جراب'], array_column($byModel->json('data'), 'name'));
        $this->assertSame(1, $byModel->json('meta.total'));
        $this->assertSame(['شاحن 20W'], array_column($this->getJson('/api/v1/public/stores/elnour/products?q='.urlencode('شاحن'))->json('data'), 'name'));
        $this->assertSame(['جراب', 'شاحن 20W'], array_column($this->getJson('/api/v1/public/stores/elnour/products?sort=price_asc')->json('data'), 'name'));
        $this->assertSame(['شاحن 20W'], array_column($this->getJson('/api/v1/public/stores/elnour/products?min=20000')->json('data'), 'name'));

        $product = $this->getJson("/api/v1/public/stores/elnour/products/{$productId}")->assertOk()->json('data');
        $this->assertSame(['جراب سيليكون', 10000, 'in'], [$product['description'], $product['price'], $product['availability']]);
        $this->assertSame([['id' => $model, 'full_name' => 'Zeta Z1']], $product['device_models']);
        // Never the cost, barcode or stock count (unless the owner shows it).
        $json = json_encode($product);
        $this->assertStringNotContainsString('CASE-1', $json);
        $this->assertStringNotContainsString('cost', $json);
        $this->assertArrayNotHasKey('quantity', $product);
    }

    public function test_online_price_hidden_products_and_stock(): void
    {
        $products = collect($this->getJson('/api/v1/products')->json('data'))->keyBy('name');
        $case = $products['جراب'];
        $this->patchJson("/api/v1/products/{$case['id']}", ['variants' => [[...$case['variants'][0], 'price_online' => 9000]]])->assertOk();
        $this->open(['show_quantity' => true]);

        $item = collect($this->getJson('/api/v1/public/stores/elnour/products')->json('data'))->firstWhere('name', 'جراب');
        $this->assertSame([9000, 'in', 50], [$item['price'], $item['availability'], $item['quantity']]);

        // Hidden from the store.
        $this->patchJson('/api/v1/products/online', ['ids' => [$case['id']], 'visible' => false])->assertOk();
        $this->assertSame(['شاحن 20W'], array_column($this->getJson('/api/v1/public/stores/elnour/products')->json('data'), 'name'));
        $this->getJson("/api/v1/public/stores/elnour/products/{$case['id']}")->assertNotFound();

        // Sold out: shown as «خلص», or hidden when the owner chooses.
        $charger = $products['شاحن 20W'];
        $this->withHeaders(['X-Branch-Id' => $this->branchId])
            ->postJson('/api/v1/inventory/adjustments', ['reason' => 'count', 'items' => [['variant_id' => $this->v[1], 'counted' => 0]]])->assertOk();
        $this->putJson('/api/v1/online-store/settings', ['show_quantity' => false])->assertOk();
        $out = $this->getJson("/api/v1/public/stores/elnour/products/{$charger['id']}")->assertOk()->json('data');
        $this->assertSame('out', $out['availability']);
        $this->putJson('/api/v1/online-store/settings', ['show_out_of_stock' => false])->assertOk();
        $this->getJson("/api/v1/public/stores/elnour/products/{$charger['id']}")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/public/stores/elnour/products')->json('data'));
    }

    public function test_the_owner_shapes_what_the_store_shows(): void
    {
        $productId = $this->getJson('/api/v1/products?q=جراب')->json('data.0.id');
        $brand = $this->postJson('/api/v1/catalog/brands', ['name' => 'Zeta'])->assertCreated()->json('data.id');
        $model = $this->postJson("/api/v1/catalog/brands/{$brand}/models", ['name' => 'Z1'])->assertCreated()->json('data.id');
        $this->patchJson("/api/v1/products/{$productId}", ['device_model_ids' => [$model]])->assertOk();
        $this->open(['show_prices' => false, 'show_models' => false, 'show_latest' => false, 'show_whatsapp' => false, 'announcement' => 'توصيل ببلاش']);

        $home = $this->getJson('/api/v1/public/stores/elnour')->assertOk()->json('data');
        $this->assertSame([false, false, 'توصيل ببلاش'], [$home['store']['show_prices'], $home['store']['show_whatsapp'], $home['store']['announcement']]);
        $this->assertSame([[], []], [$home['device_brands'], $home['latest']]);

        // «اسأل عن السعر»: no price leaves the server, and it can't be guessed by filtering.
        $list = $this->getJson('/api/v1/public/stores/elnour/products?sort=price_asc')->json('data');
        $this->assertSame([null, null], array_column($list, 'price'));
        $this->assertCount(2, $this->getJson('/api/v1/public/stores/elnour/products?min=20000')->json('data'));
        $product = $this->getJson("/api/v1/public/stores/elnour/products/{$productId}")->json('data');
        $this->assertSame([null, null, []], [$product['price'], $product['variants'][0]['price'], $product['device_models']]);
        $this->assertStringNotContainsString('10000', json_encode($product));

        // Orders in the app need the prices shown.
        $this->putJson('/api/v1/online-store/settings', ['mode' => 'orders'])->assertUnprocessable()->assertJsonPath('code', 'orders_need_prices');
        $this->putJson('/api/v1/online-store/settings', ['mode' => 'orders', 'show_prices' => true])->assertOk();
        $this->putJson('/api/v1/online-store/settings', ['show_prices' => false])->assertUnprocessable()->assertJsonPath('code', 'orders_need_prices');
    }

    public function test_brand_and_category_names_on_the_store(): void
    {
        $cases = $this->getJson('/api/v1/catalog/categories')->json('data');
        $casesId = collect($cases)->firstWhere('name', 'جرابات')['id'];
        $brand = $this->postJson('/api/v1/catalog/brands', ['name' => 'Zeta'])->assertCreated()->json('data.id');
        $productId = $this->getJson('/api/v1/products?q=جراب')->json('data.0.id');
        $this->patchJson("/api/v1/products/{$productId}", ['brand_id' => $brand])->assertOk();

        // Unknown categories and names equal to the app's are dropped.
        $saved = $this->open(['show_brand' => false, 'category_names' => [$casesId => 'كفرات', 999999 => 'x', 1 => '']]);
        $this->assertSame([(string) $casesId => 'كفرات'], (array) $saved['category_names']);

        $home = $this->getJson('/api/v1/public/stores/elnour')->json('data');
        $this->assertContains('كفرات', array_column($home['categories'], 'name'));
        $product = $this->getJson("/api/v1/public/stores/elnour/products/{$productId}")->json('data');
        $this->assertSame(['كفرات', null], [$product['category']['name'], $product['brand']]);
    }

    public function test_receipt_paper(): void
    {
        $this->assertSame('80', $this->getJson('/api/v1/auth/me')->json('data.tenant.receipt.paper'));
        $this->putJson('/api/v1/shop/profile', ['name' => 'محل 1', 'phone' => '01000000001', 'paper' => '57'])->assertUnprocessable();
        $this->assertSame('58', $this->putJson('/api/v1/shop/profile', ['name' => 'محل 1', 'phone' => '01000000001', 'paper' => '58'])->assertOk()->json('data.receipt.paper'));
    }

    public function test_receipt_switches(): void
    {
        $this->assertSame([true, true, true], array_values(array_intersect_key(
            $this->getJson('/api/v1/auth/me')->json('data.tenant.receipt'), array_flip(['show_cashier', 'show_customer', 'show_serials']),
        )));
        $receipt = $this->putJson('/api/v1/shop/profile', ['name' => 'محل 1', 'phone' => '01000000001', 'show_cashier' => false, 'show_serials' => false])->assertOk()->json('data.receipt');
        $this->assertSame([false, true, false], [$receipt['show_cashier'], $receipt['show_customer'], $receipt['show_serials']]);
        // Saving the profile without them keeps them.
        $this->assertFalse($this->putJson('/api/v1/shop/profile', ['name' => 'محل 1', 'phone' => '01000000001', 'footer' => 'شكراً'])->assertOk()->json('data.receipt.show_cashier'));
    }

    public function test_stores_are_kept_apart_and_closing_hides_them(): void
    {
        $this->open();
        $mine = $this->getJson('/api/v1/products')->json('data.0.id');

        $other = $this->registerShop(ShopType::Accessories);
        Sanctum::actingAs($other);
        app(PermissionResolver::class)->forget();
        $this->putJson('/api/v1/online-store/settings', ['slug' => 'other-shop', 'mode' => 'whatsapp'])->assertOk();
        // Another shop's product id through this store: not found.
        $this->getJson("/api/v1/public/stores/other-shop/products/{$mine}")->assertNotFound();
        $this->assertSame([], $this->getJson('/api/v1/public/stores/other-shop/products')->json('data'));

        Sanctum::actingAs($this->owner);
        $this->putJson('/api/v1/online-store/settings', ['mode' => 'off'])->assertOk();
        $this->getJson('/api/v1/public/stores/elnour')->assertNotFound();
    }

    public function test_logo_and_cover_are_public_images(): void
    {
        $store = $this->post('/api/v1/online-store/media/logo', ['image' => UploadedFile::fake()->image('logo.png', 600, 600)], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertCount(2, $store['logo']);
        $this->get($store['logo'][512])->assertOk()->assertHeader('Content-Type', 'image/webp');
        $old = $store['logo'][512];
        $store = $this->post('/api/v1/online-store/media/logo', ['image' => UploadedFile::fake()->image('logo2.png', 300, 300)], ['Accept' => 'application/json'])->assertOk()->json('data');
        $this->assertNotSame($old, $store['logo'][512], 'a new name, so caches never show the old one');
        $this->get($old)->assertNotFound();
        $this->deleteJson('/api/v1/online-store/media/logo')->assertOk()->assertJsonPath('data.logo', null);
    }

    public function test_settings_need_the_permission(): void
    {
        Sanctum::actingAs($this->staff('cashier'));
        $this->getJson('/api/v1/online-store/settings')->assertForbidden();
    }

    public function test_stores_live_on_subdomains_of_the_store_host(): void
    {
        config(['services.store.host' => 'muhasebi.com', 'services.store.url' => 'https://{slug}.muhasebi.com']);
        $this->assertSame('https://elnour.muhasebi.com', $this->open()['url']);

        // Caddy's question before an HTTPS certificate: only open stores' subdomains.
        $this->getJson('/api/v1/public/stores-tls?domain=elnour.muhasebi.com')->assertOk();
        $this->getJson('/api/v1/public/stores-tls?domain=ELNOUR.muhasebi.com.')->assertOk();
        foreach (['other.muhasebi.com', 'app.muhasebi.com', 'elnour.evil.com', 'muhasebi.com', 'a.elnour.muhasebi.com', ''] as $domain) {
            $this->getJson('/api/v1/public/stores-tls?domain='.urlencode($domain))->assertNotFound();
        }
        // Names of the platform's own subdomains can't be taken.
        foreach (['app', 'admin', 'www', 'api', 'mail'] as $reserved) {
            $this->putJson('/api/v1/online-store/settings', ['slug' => $reserved])->assertUnprocessable()->assertJsonPath('code', 'slug_invalid');
        }
        $this->putJson('/api/v1/online-store/settings', ['mode' => 'off'])->assertOk();
        $this->getJson('/api/v1/public/stores-tls?domain=elnour.muhasebi.com')->assertNotFound();
    }
}
