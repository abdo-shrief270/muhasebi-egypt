<?php

namespace Tests\Feature\Marketplace;

use App\Modules\Catalog\Models\DeviceModel;
use App\Modules\Identity\PermissionResolver;
use App\Modules\Marketplace\Support\MarketIndexer;
use App\Modules\UsedDevices\Contracts\UsedListings;
use App\Modules\UsedDevices\Models\UsedDevice;
use App\Modules\UsedDevices\Models\UsedDevicePhoto;
use App\Modules\UsedDevices\Support\Imei;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** «سوق محاسبي» phase 3: used devices with their grade, battery, inspection and photos. */
class UsedMarketTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** @var list<array{method: string, path: string, body: string}> */
    private array $sent = [];

    private array $hit = [];

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        config(['services.search' => ['url' => 'https://search.test', 'api_key' => 'K', 'username' => '', 'password' => '', 'ca' => '', 'prefix' => 'muhasebi_', 'timeout' => 5]]);
        Http::fake(function (Request $request) {
            $path = (string) parse_url($request->url(), PHP_URL_PATH);
            $this->sent[] = ['method' => $request->method(), 'path' => $path, 'body' => $request->body()];

            return match (true) {
                str_ends_with($path, '/_search') => Http::response(['hits' => ['total' => ['value' => $this->hit ? 1 : 0], 'hits' => $this->hit ? [$this->hit] : []], 'aggregations' => []]),
                str_ends_with($path, '/_count') => Http::response(['count' => 1]),
                str_ends_with($path, '/_bulk') => Http::response(['errors' => false, 'items' => []]),
                default => Http::response(['acknowledged' => true]),
            };
        });
        $this->openShopWithStock();
        $this->openShift(1_000_000);
        app(PermissionResolver::class)->forget();
        $this->patchJson("/api/v1/branches/{$this->branchId}", ['governorate' => 'cairo', 'latitude' => 29.96, 'longitude' => 31.25])->assertOk();
    }

    private function buy(array $overrides = []): array
    {
        $imei = '35123456789012';
        for ($d = 0; ! Imei::isValid($imei.$d); $d++);

        return $this->post('/api/v1/used-devices', [
            'seller_name' => 'محمود سعيد',
            'seller_phone' => '01012345678',
            'seller_national_id' => '29001010112351',
            'device_model_id' => $this->inShop(fn () => DeviceModel::query()->where('name', 'iPhone 13')->value('id')),
            'storage' => '128GB',
            'color' => 'أسود',
            'imei' => $imei.$d,
            'grade' => 'B',
            'checklist' => ['account_removed' => 'yes', 'screen' => 'yes', 'body' => 'no'],
            'battery_health' => 86,
            'purchase_price' => 1_500_000,
            'asking_price' => 1_800_000,
            'payment_method' => 'cash',
            'id_front' => UploadedFile::fake()->image('front.jpg', 600, 380),
            'id_back' => UploadedFile::fake()->image('back.jpg', 600, 380),
            'device_photos' => [UploadedFile::fake()->image('phone.jpg', 400, 800)],
            ...$overrides,
        ], ['Accept' => 'application/json', 'X-Branch-Id' => $this->branchId])->assertCreated()->json('data');
    }

    public function test_a_used_device_shows_with_its_grade_battery_checks_and_photos_only(): void
    {
        $device = $this->buy();
        $model = $this->inShop(fn () => UsedDevice::query()->findOrFail($device['id']));
        $doc = app(MarketIndexer::class)->documents($this->owner->tenant_id)["{$model->variant_id}:{$this->branchId}"];
        $this->assertSame(['used', 'B', 86, 128, 'أسود', 1], [$doc['condition'], $doc['grade'], $doc['battery_health'], $doc['storage_gb'], $doc['color'], $doc['images']]);
        $this->assertStringStartsWith("/api/v1/public/media/used/{$model->id}/", $doc['image']);
        $this->assertStringNotContainsString($model->imei, json_encode($doc));

        // The device photo is public WebP; the card photos never are.
        $this->get($doc['image'])->assertOk()->assertHeader('Content-Type', 'image/webp');
        $card = $this->inShop(fn () => UsedDevicePhoto::query()->where('used_device_id', $model->id)->where('kind', 'id_front')->value('id'));
        $this->get("/api/v1/public/media/used/{$model->id}/{$card}.webp")->assertNotFound();

        // The offer page carries the inspection, never the seller or the IMEI.
        $this->hit = ['_id' => "{$model->variant_id}:{$this->branchId}", '_source' => [...$doc, 'shop' => ['slug' => 'x', 'name' => 'النور']]];
        $offer = $this->getJson("/api/v1/public/market/offers/{$model->variant_id}:{$this->branchId}")->assertOk()->json('data');
        $this->assertSame(['B', 'حالة كويسة', 86, true], [$offer['used']['grade'], $offer['used']['grade_label'], $offer['used']['battery_health'], $offer['used']['imei_registered']]);
        $this->assertContains(['label' => 'الشاشة سليمة (من غير كسر ولا بقع)', 'ok' => true], $offer['used']['checks']);
        $this->assertContains(['label' => 'الجسم والضهر من غير كسر', 'ok' => false], $offer['used']['checks']);
        $this->assertArrayNotHasKey('tenant_id', $offer['offer']);
        $this->assertStringNotContainsString($model->imei, json_encode($offer));
        $this->assertStringNotContainsString('محمود', json_encode($offer));

        // Sold: its photos stop being served.
        $this->inShop(fn () => UsedDevice::query()->whereKey($model->id)->update(['status' => 'sold']));
        $this->get($doc['image'])->assertNotFound();
    }

    public function test_no_photo_no_listing_until_the_shop_adds_one(): void
    {
        $device = $this->buy(['device_photos' => []]);
        $model = $this->inShop(fn () => UsedDevice::query()->findOrFail($device['id']));
        $key = "{$model->variant_id}:{$this->branchId}";
        $this->assertNull(app(MarketIndexer::class)->documents($this->owner->tenant_id)[$key]);
        $this->getJson('/api/v1/marketplace/settings')->assertOk()->assertJsonPath('data.status.used_without_photos', 1);

        $since = now()->subSecond();
        $headers = ['Accept' => 'application/json', 'X-Branch-Id' => $this->branchId];
        $added = $this->post("/api/v1/used-devices/{$model->id}/photos", ['photos' => [UploadedFile::fake()->image('a.jpg', 400, 800), UploadedFile::fake()->image('b.jpg', 400, 800)]], $headers)->assertOk();
        $this->assertSame([$model->variant_id], app(UsedListings::class)->changedSince($since)[$this->owner->tenant_id]);
        $this->assertSame(2, app(MarketIndexer::class)->documents($this->owner->tenant_id)[$key]['images']);
        $this->getJson('/api/v1/marketplace/settings')->assertJsonPath('data.status.used_without_photos', 0);

        // At most 4; one removed again; the card photos can't be removed this way.
        $this->post("/api/v1/used-devices/{$model->id}/photos", ['photos' => array_fill(0, 3, UploadedFile::fake()->image('c.jpg'))], $headers)
            ->assertUnprocessable()->assertJsonPath('code', 'device_photos_limit');
        $photos = $this->inShop(fn () => UsedDevicePhoto::query()->where('used_device_id', $model->id)->pluck('id', 'kind')->all());
        $this->delete("/api/v1/used-devices/{$model->id}/photos/{$photos['id_front']}", [], $headers)->assertNotFound();
        $this->delete("/api/v1/used-devices/{$model->id}/photos/{$photos['device']}", [], $headers)->assertOk();
        $this->assertSame(1, app(MarketIndexer::class)->documents($this->owner->tenant_id)[$key]['images']);
        $this->assertDatabaseHas('audit_log', ['action' => 'used_devices.photos_added']);
    }

    public function test_search_filters_used_devices_by_grade_battery_and_storage(): void
    {
        $this->getJson('/api/v1/public/market/search?category=used&grade[]=A&grade[]=B&battery=85&storage=128')->assertOk();
        $body = collect($this->sent)->last()['body'];
        $this->assertStringContainsString('{"terms":{"grade":["A","B"]}}', $body);
        $this->assertStringContainsString('{"range":{"battery_health":{"gte":85}}}', $body);
        $this->assertStringContainsString('{"term":{"storage_gb":128}}', $body);
        $this->getJson('/api/v1/public/market/search?grade[]=Z')->assertUnprocessable();
        $this->getJson('/api/v1/public/market/options')->assertOk()->assertJsonPath('data.grades.0', ['key' => 'A', 'label' => 'زي الجديد']);
    }
}
