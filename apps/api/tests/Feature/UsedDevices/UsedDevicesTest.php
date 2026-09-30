<?php

namespace Tests\Feature\UsedDevices;

use App\Modules\Cash\Models\CashMovement;
use App\Modules\Catalog\Models\DeviceModel;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Identity\Models\Role;
use App\Modules\Inventory\Models\SerialNumber;
use App\Modules\UsedDevices\Models\UsedDevice;
use App\Modules\UsedDevices\Models\UsedDevicePhoto;
use App\Modules\UsedDevices\Models\UsedDeviceSeller;
use App\Modules\UsedDevices\Support\Imei;
use App\Modules\UsedDevices\Support\NationalId;
use App\Support\Audit\AuditEntry;
use App\Support\Events\EventRelay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class UsedDevicesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** Born 1 Jan 1990 in Cairo, male. */
    private const NID = '29001010112351';

    private int $iphone13;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');
        $this->openShopWithStock();
        $this->openShift(1_000_000);
        $this->iphone13 = $this->inShop(fn () => DeviceModel::query()->where('name', 'iPhone 13')->value('id'));
    }

    /** 14 digits + the Luhn check digit. */
    private static function imei(string $first14): string
    {
        for ($d = 0; $d <= 9; $d++) {
            if (Imei::isValid($first14.$d)) {
                return $first14.$d;
            }
        }
        throw new \LogicException('unreachable');
    }

    private function buy(array $overrides = [])
    {
        return $this->post('/api/v1/used-devices', [
            'seller_name' => 'محمود سعيد',
            'seller_phone' => '01012345678',
            'seller_national_id' => self::NID,
            'device_model_id' => $this->iphone13,
            'storage' => '128GB',
            'color' => 'أسود',
            'imei' => self::imei('35123456789012'),
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
        ], ['Accept' => 'application/json']);
    }

    /** Pretends the device was bought $days days ago (moving the clock would end the shop's trial). */
    private function age(string $deviceId, int $days): void
    {
        DB::table('used_devices')->where('id', $deviceId)->update(['bought_at' => now()->subDays($days)]);
    }

    private function relay(): void
    {
        app(EventRelay::class)->publishPending();
    }

    private function staffWith(array $permissions)
    {
        $user = $this->staff('cashier');
        $this->inShop(fn () => Role::query()->where('key', 'cashier')->update(['permissions' => json_encode($permissions)]));

        return $user->refresh();
    }

    public function test_national_ids_are_checked_and_read(): void
    {
        [$id] = NationalId::parse(self::NID);
        $this->assertSame(['1990-01-01', 'male', 'القاهرة'], [$id->birthDate->toDateString(), $id->gender, $id->governorate()]);
        [$female] = NationalId::parse('30512312101242');
        $this->assertSame(['2005-12-31', 'female', 'الجيزة'], [$female->birthDate->toDateString(), $female->gender, $female->governorate()]);
        $this->assertNotNull(NationalId::parse('٢٩٠٠١٠١٠١١٢٣٥١')[0], 'Arabic digits are folded');

        $bad = [
            '2900101011235' => 'الرقم القومي لازم يبقى 14 رقم.',
            '19001010112351' => 'أول رقم في الرقم القومي لازم يبقى 2 أو 3.',
            '29002300112351' => 'تاريخ الميلاد اللي في الرقم القومي مش صحيح.',
            '29013010112351' => 'تاريخ الميلاد اللي في الرقم القومي مش صحيح.',
            '39001010112351' => 'تاريخ الميلاد اللي في الرقم القومي لسه ماجاش.',
            '29001019912351' => 'كود المحافظة اللي في الرقم القومي مش صحيح.',
            '29001010512351' => 'كود المحافظة اللي في الرقم القومي مش صحيح.',
        ];
        foreach ($bad as $number => $message) {
            $this->assertSame([null, $message], NationalId::parse((string) $number, CarbonImmutable::parse('2026-10-01')), $number);
        }

        $check = $this->getJson('/api/v1/used-devices/national-id-check?national_id='.self::NID)->assertOk()->json('data');
        $this->assertSame([true, '1990-01-01', 'ذكر', 'القاهرة', false], [$check['valid'], $check['birth_date'], $check['gender_label'], $check['governorate'], $check['known']]);
        $this->getJson('/api/v1/used-devices/national-id-check?national_id=29001019912351')->assertOk()->assertJsonPath('data.valid', false);

        $this->buy(['seller_national_id' => '29002300112351'])->assertStatus(422)->assertJsonPath('code', 'national_id_invalid');
    }

    public function test_imeis_need_a_valid_luhn_digit_and_one_in_stock_is_refused(): void
    {
        $this->assertTrue(Imei::isValid('490154203237518'));
        $this->assertTrue(Imei::isValid('49-015420-323751-8'));
        $this->assertFalse(Imei::isValid('490154203237519'));
        $this->assertFalse(Imei::isValid('49015420323751'));

        $this->buy(['imei' => '490154203237519'])->assertStatus(422)->assertJsonPath('code', 'imei_invalid');
        $this->buy(['imei2' => '490154203237519'])->assertStatus(422)->assertJsonPath('code', 'imei_invalid');
        $this->buy(['checklist' => ['account_removed' => 'no']])->assertStatus(422)->assertJsonPath('code', 'account_not_removed');

        $this->buy()->assertCreated();
        $this->buy()->assertStatus(422)->assertJsonPath('code', 'imei_in_stock');
        $this->assertSame(1, $this->inShop(fn () => UsedDevice::query()->count()), 'nothing half-saved');

        $check = $this->getJson('/api/v1/used-devices/imei-check?imei='.self::imei('35123456789012'))->assertOk()->json('data');
        $this->assertSame([true, true], [$check['valid'], $check['in_stock']]);
    }

    public function test_buying_pays_from_the_drawer_and_puts_one_sellable_unit_in_stock(): void
    {
        $device = $this->buy()->assertCreated()->json('data');

        $this->assertSame(['UD-00001', 'Apple iPhone 13 128GB أسود', 'in_stock', 1_500_000, 1_800_000], [$device['reference'], $device['title'], $device['status'], $device['purchase_price'], $device['asking_price']]);
        $this->assertSame(['محمود سعيد', '+201012345678', self::NID, 'male'], [$device['seller']['name'], $device['seller']['phone'], $device['seller']['national_id'], $device['seller']['gender']]);
        $this->assertSame(['id_front', 'id_back', 'device'], array_column($device['photos'], 'kind'));
        $this->assertSame('yes', collect($device['checklist'])->firstWhere('key', 'account_removed')['value']);
        $this->assertSame('na', collect($device['checklist'])->firstWhere('key', 'cameras')['value'], 'unanswered checks are "couldn\'t check"');

        // Paid in cash out of this user's drawer.
        $movement = $this->inShop(fn () => CashMovement::query()->where('ref_type', 'used_device')->sole());
        $this->assertSame([-1_500_000, 'used_device_purchase', 'cash'], [$movement->amount, $movement->type->value, $movement->method->value]);

        // Its own variant under «Apple iPhone 13 مستعمل», at its asking price, tracking IMEIs, with its IMEI in stock at its cost.
        $variant = $this->inShop(fn () => ProductVariant::query()->with('product.category')->findOrFail($device['variant_id']));
        $this->assertSame(['Apple iPhone 13 مستعمل', 'موبايلات مستعملة', true, 1_800_000], [$variant->product->name, $variant->product->category->name, $variant->product->track_serial, $variant->price_retail]);
        $this->assertStringContainsString('UD-00001', (string) $variant->name);
        $serial = $this->inShop(fn () => SerialNumber::query()->where('serial', $device['imei'])->sole());
        $this->assertSame(['in_stock', $device['variant_id']], [$serial->status, $serial->variant_id]);
        $this->assertSame(1, (int) $this->inShop(fn () => DB::table('stock_levels')->where('variant_id', $device['variant_id'])->value('qty')));
        $this->assertSame(1_500_000, (int) $this->inShop(fn () => DB::table('stock_levels')->where('variant_id', $device['variant_id'])->value('avg_cost')));

        // A second device of the same model is another variant of the same product; a wallet payment skips the drawer.
        $second = $this->buy(['imei' => self::imei('35123456789099'), 'payment_method' => 'wallet', 'grade' => 'A'])->assertCreated()->json('data');
        $this->assertSame($variant->product_id, $this->inShop(fn () => ProductVariant::query()->findOrFail($second['variant_id'])->product_id));
        $this->assertSame(1, $this->inShop(fn () => CashMovement::query()->where('ref_type', 'used_device')->count()));
        $this->assertSame(1, $this->inShop(fn () => UsedDeviceSeller::query()->count()), 'same national ID = same seller');

        $this->assertSame(2, AuditEntry::query()->where('action', 'used_devices.bought')->count());
        $this->assertSame(0, AuditEntry::query()->where('description', 'like', '%محمود%')->count(), 'the seller\'s name stays out of the audit log');

        // The national ID is encrypted at rest.
        $raw = DB::table('used_device_sellers')->value('national_id');
        $this->assertStringNotContainsString(self::NID, (string) $raw);

        // The list and the asking price.
        $list = $this->getJson('/api/v1/used-devices?status=in_stock')->assertOk()->json();
        $this->assertSame([2, 2], [count($list['data']), $list['summary']['in_stock']]);
        $this->assertSame(['UD-00002'], array_column($this->getJson('/api/v1/used-devices?grade=A')->json('data'), 'reference'));
        $this->assertSame(['UD-00001'], array_column($this->getJson('/api/v1/used-devices?q=UD-1')->json('data'), 'reference'));
        $this->patchJson("/api/v1/used-devices/{$device['id']}", ['asking_price' => 1_750_000])->assertOk()->assertJsonPath('data.asking_price', 1_750_000);
        $this->assertSame(1_750_000, $this->inShop(fn () => ProductVariant::query()->findOrFail($device['variant_id'])->price_retail));
        $this->assertSame(1, AuditEntry::query()->where('action', 'used_devices.price_changed')->count());
        $this->assertSame(1, $this->inShop(fn () => DB::table('price_changes')->where('variant_id', $device['variant_id'])->count()));
    }

    public function test_cash_needs_an_open_shift(): void
    {
        DB::table('cash_shifts')->update(['closed_at' => now()]);

        $this->buy()->assertStatus(409)->assertJsonPath('code', 'shift_not_open');
        $this->buy(['payment_method' => 'instapay'])->assertCreated();
    }

    public function test_it_sells_at_the_pos_by_its_imei_and_its_profit_is_its_own(): void
    {
        $device = $this->buy()->assertCreated()->json('data');

        // The POS finds it by scanning the IMEI, then sells it at its own price.
        [$found] = $this->getJson('/api/v1/inventory/serials?'.http_build_query(['q' => $device['imei'], 'in_stock' => 1]))->assertOk()->json('data');
        $this->assertSame($device['variant_id'], $found['variant']['id']);
        $sale = $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $device['variant_id'], 'qty' => 1, 'serials' => [$device['imei']]], ['variant_id' => $this->v[0], 'qty' => 1]],
            'discount' => 19_000,
            'payments' => [['method' => 'cash', 'amount' => 1_791_000]],
        ])->assertCreated()->json('data');
        $this->assertSame(1_500_000, (int) DB::table('sale_items')->where('variant_id', $device['variant_id'])->value('unit_cost'), 'its cost is what was paid for it');
        $this->relay();

        $sold = $this->getJson("/api/v1/used-devices/{$device['id']}")->assertOk()->json('data');
        // 1,800,000 of a 1,810,000 subtotal carries 1,800,000 / 1,810,000 of the 19,000 discount.
        $this->assertSame(['sold', $sale['id'], 1_781_105, 281_105], [$sold['status'], $sold['sale']['id'], $sold['sale']['price'], $sold['profit']]);
        $this->assertFalse($this->inShop(fn () => ProductVariant::query()->findOrFail($device['variant_id'])->is_active), 'off the POS once sold');
        $this->assertSame([], $this->getJson('/api/v1/used-devices?status=in_stock')->json('data'));

        // Buying it back later: the IMEI's story shows the sale.
        $check = $this->getJson('/api/v1/used-devices/imei-check?imei='.$device['imei'])->json('data');
        $this->assertSame([false, true, ['used_purchase', 'sale']], [$check['in_stock'], $check['sold_before'], array_column($check['events'], 'type')]);
        $this->assertSame(['UD-00001'], array_column($check['previous'], 'reference'));

        // The customer returns it: in stock again, for sale again.
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => true, 'serials' => [$device['imei']]]]])->assertCreated();
        $this->relay();
        $back = $this->getJson("/api/v1/used-devices/{$device['id']}")->json('data');
        $this->assertSame(['in_stock', null, null], [$back['status'], $back['sale'], $back['profit']]);
        $this->assertTrue($this->inShop(fn () => ProductVariant::query()->findOrFail($device['variant_id'])->is_active));
    }

    public function test_seller_data_and_card_photos_need_their_permission(): void
    {
        $device = $this->buy()->assertCreated()->json('data');
        $photos = collect($device['photos'])->keyBy('kind');

        // The owner sees the card; each view is audited; the file on disk is encrypted.
        $response = $this->get("/api/v1/used-devices/{$device['id']}/photos/{$photos['id_front']['id']}");
        $response->assertOk()->assertHeader('Content-Type', 'image/jpeg');
        $this->assertSame(1, AuditEntry::query()->where('action', 'used_devices.id_photo_viewed')->count());
        $path = $this->inShop(fn () => UsedDevicePhoto::query()->findOrFail($photos['id_front']['id'])->path);
        $this->assertSame($response->getContent(), Crypt::decryptString((string) Storage::disk('local')->get($path)));
        $this->assertStringNotContainsString('JFIF', (string) Storage::disk('local')->get($path));

        $manager = $this->staff('manager');
        $limited = $this->staffWith(['used_devices.manage']);

        // Someone who buys used devices but may not see sellers.
        Sanctum::actingAs($limited);
        $seen = $this->getJson("/api/v1/used-devices/{$device['id']}")->assertOk()->json('data');
        $this->assertSame([null, true, ['device'], null], [$seen['seller'], $seen['seller_hidden'], array_column($seen['photos'], 'kind'), $seen['purchase_price']]);
        $this->get("/api/v1/used-devices/{$device['id']}/photos/{$photos['id_front']['id']}")->assertForbidden();
        $this->get("/api/v1/used-devices/{$device['id']}/photos/{$photos['device']['id']}")->assertOk();
        $this->getJson('/api/v1/used-devices/sellers?q='.self::NID)->assertForbidden();
        $this->assertNull($this->getJson('/api/v1/used-devices/national-id-check?national_id='.self::NID)->json('data.seller'));
        $this->assertSame(1, $this->getJson('/api/v1/used-devices/national-id-check?national_id='.self::NID)->json('data.devices_count'), 'but knows this person sold here before');

        // A manager sees sellers (the migration gives it to existing manager roles too).
        $this->assertContains('used_devices.view_seller', $this->inShop(fn () => Role::query()->where('key', 'manager')->value('permissions')));
        Sanctum::actingAs($manager);
        $this->getJson("/api/v1/used-devices/{$device['id']}")->assertOk()->assertJsonPath('data.seller.national_id', self::NID);

        // No permission at all.
        Sanctum::actingAs($this->staffWith(['products.view']));
        $this->getJson("/api/v1/used-devices/{$device['id']}")->assertForbidden();
        $this->get("/api/v1/used-devices/{$device['id']}/photos/{$photos['device']['id']}", ['Accept' => 'application/json'])->assertForbidden();
    }

    public function test_seller_lookup_by_national_id_phone_or_name(): void
    {
        $this->buy()->assertCreated();
        $this->buy(['imei' => self::imei('35999988887777'), 'seller_name' => 'سارة', 'seller_phone' => '01198765432', 'seller_national_id' => '30512312101242'])->assertCreated();

        $this->assertSame(['محمود سعيد'], array_column($this->getJson('/api/v1/used-devices/sellers?q='.self::NID)->json('data'), 'name'));
        $this->assertSame(['سارة'], array_column($this->getJson('/api/v1/used-devices/sellers?q=01198765432')->json('data'), 'name'));
        $found = $this->getJson('/api/v1/used-devices/sellers?q=محمود')->json('data');
        $this->assertSame([1, self::NID], [$found[0]['devices_count'], $found[0]['national_id']]);
        $this->assertCount(1, $this->getJson("/api/v1/used-devices/sellers/{$found[0]['id']}")->json('data.devices'));
    }

    public function test_photo_uploads_are_limited(): void
    {
        $this->buy(['id_back' => null])->assertStatus(422)->assertJsonValidationErrors('id_back');
        $this->buy(['id_front' => UploadedFile::fake()->create('front.pdf', 100, 'application/pdf')])->assertStatus(422)->assertJsonValidationErrors('id_front');
        $this->buy(['id_front' => UploadedFile::fake()->image('front.jpg')->size(6000)])->assertStatus(422)->assertJsonValidationErrors('id_front');
        $this->buy(['device_photos' => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.jpg"), range(1, 5))])->assertStatus(422)->assertJsonValidationErrors('device_photos');
        $this->assertSame([], Storage::disk('local')->allFiles());

        $this->buy(['device_photos' => array_map(fn ($i) => UploadedFile::fake()->image("p{$i}.png"), range(1, 4))])->assertCreated();
        $this->assertCount(6, Storage::disk('local')->allFiles());
    }

    public function test_erasure_keeps_the_id_for_the_retention_period_then_purges_it(): void
    {
        $device = $this->buy()->assertCreated()->json('data');
        $sellerId = $device['seller']['id'];
        $this->getJson('/api/v1/used-devices/settings')->assertOk()->assertJsonPath('data.id_retention_years', 3);

        // Only the owner erases.
        Sanctum::actingAs($this->staff('manager'));
        $this->postJson("/api/v1/used-devices/sellers/{$sellerId}/erase")->assertForbidden();
        Sanctum::actingAs($this->owner);

        $erased = $this->postJson("/api/v1/used-devices/sellers/{$sellerId}/erase")->assertOk()->json('data');
        $this->assertSame(['عميل محذوف', null, true, self::NID, false], [$erased['name'], $erased['phone'], $erased['erased'], $erased['national_id'], $erased['id_purged']]);
        // Still found by the national ID (the anti-theft record), card photos still there.
        $this->assertCount(1, $this->getJson('/api/v1/used-devices/sellers?q='.self::NID)->json('data'));
        $this->assertCount(3, Storage::disk('local')->allFiles());

        // Not yet: 3 years from the purchase.
        $this->age($device['id'], 3 * 365 - 5);
        $this->artisan('used-devices:purge-ids')->assertSuccessful();
        $this->assertNotNull($this->inShop(fn () => UsedDeviceSeller::query()->findOrFail($sellerId)->national_id));

        $this->age($device['id'], 3 * 365 + 5);
        $this->artisan('used-devices:purge-ids')->assertSuccessful();
        $seller = $this->inShop(fn () => UsedDeviceSeller::query()->findOrFail($sellerId));
        $this->assertSame([null, null, null], [$seller->national_id, $seller->national_id_hash, $seller->birth_date]);
        $this->assertNotNull($seller->id_purged_at);
        $this->assertSame(['device'], $this->inShop(fn () => UsedDevicePhoto::query()->pluck('kind')->all()));
        $this->assertCount(1, Storage::disk('local')->allFiles(), 'the card files are deleted; the device photo stays');
        $this->assertSame(1_500_000, $this->inShop(fn () => UsedDevice::query()->findOrFail($device['id'])->purchase_price), 'the device and its money stay');
        $this->assertSame([], $this->getJson('/api/v1/used-devices/sellers?q='.self::NID)->json('data'));
        $this->assertSame(1, AuditEntry::query()->where('action', 'used_devices.seller_id_purged')->count());
    }

    public function test_a_shorter_retention_and_a_seller_who_sells_again(): void
    {
        $this->putJson('/api/v1/used-devices/settings', ['id_retention_years' => 0])->assertUnprocessable();
        $this->putJson('/api/v1/used-devices/settings', ['id_retention_years' => 1])->assertOk();
        $first = $this->buy()->assertCreated()->json('data');
        $this->postJson("/api/v1/used-devices/sellers/{$first['seller']['id']}/erase")->assertOk();

        // They come back and sell again: on record again, and the clock restarts from this sale.
        $this->age($first['id'], 400);
        $again = $this->buy(['imei' => self::imei('35111122223333')])->assertCreated()->json('data');
        $this->assertSame([$first['seller']['id'], 'محمود سعيد'], [$again['seller']['id'], $again['seller']['name']]);
        $this->postJson("/api/v1/used-devices/sellers/{$first['seller']['id']}/erase")->assertOk();

        $this->age($again['id'], 200);
        $this->artisan('used-devices:purge-ids');
        $this->assertNotNull($this->inShop(fn () => UsedDeviceSeller::query()->findOrFail($first['seller']['id'])->national_id));
        $this->age($again['id'], 370);
        $this->artisan('used-devices:purge-ids');
        $this->assertNull($this->inShop(fn () => UsedDeviceSeller::query()->findOrFail($first['seller']['id'])->national_id));
    }

    public function test_erasing_the_customer_with_the_same_phone_erases_the_seller(): void
    {
        $device = $this->buy()->assertCreated()->json('data');
        $customer = $this->postJson('/api/v1/customers', ['name' => 'محمود سعيد', 'phone' => '01012345678'])->assertCreated()->json('data');

        $this->postJson("/api/v1/customers/{$customer['id']}/erase")->assertOk();
        $this->relay();

        $seller = $this->inShop(fn () => UsedDeviceSeller::query()->findOrFail($device['seller']['id']));
        $this->assertSame(['عميل محذوف', null, self::NID], [$seller->name, $seller->phone, $seller->national_id]);
        $this->assertNotNull($seller->erased_at);
    }

    public function test_the_report_counts_devices_profit_and_stock_age(): void
    {
        $a = $this->buy()->assertCreated()->json('data');
        $this->buy(['imei' => self::imei('35123456789099'), 'grade' => 'A', 'purchase_price' => 2_000_000, 'asking_price' => 2_400_000])->assertCreated();
        $this->buy(['imei' => self::imei('35777766665555'), 'device_model_id' => null, 'model_name' => 'Nokia 3310', 'grade' => 'C', 'purchase_price' => 50_000, 'asking_price' => 90_000])->assertCreated();

        $this->travel(4)->days();
        $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $a['variant_id'], 'qty' => 1, 'serials' => [$a['imei']]]],
            'payments' => [['method' => 'cash', 'amount' => 1_800_000]],
        ])->assertCreated();
        $this->relay();

        $query = fn (string $group) => $this->getJson('/api/v1/reports/used_devices?'.http_build_query(['from' => now()->subDays(10)->toDateString(), 'to' => now()->toDateString(), 'options' => ['group' => $group]]))->assertOk()->json('data');

        $byDevice = $query('device');
        $summary = collect($byDevice['summary'])->pluck('value', 'label');
        $this->assertSame([3, 3_550_000, 1, 1_800_000, 300_000, 2, 2_050_000], [
            $summary['اشتريت'], $summary['دفعت فيهم'], $summary['بعت'], $summary['إيراد البيع'], $summary['المكسب'], $summary['في المخزن دلوقتي'], $summary['قيمتها بالتكلفة'],
        ]);
        $row = collect($byDevice['rows'])->firstWhere('reference', 'UD-00001');
        $this->assertSame([1_500_000, 1_800_000, 300_000, 4], [$row['purchase_price'], $row['sale_price'], $row['profit'], $row['days']]);
        $this->assertSame(300_000, $byDevice['totals']['profit']);

        $byModel = collect($query('model')['rows'])->keyBy('name');
        $this->assertSame([2, 1, 300_000, 1, 4], [$byModel['Apple iPhone 13']['bought'], $byModel['Apple iPhone 13']['sold'], $byModel['Apple iPhone 13']['profit'], $byModel['Apple iPhone 13']['in_stock'], $byModel['Apple iPhone 13']['days_to_sell']]);
        $this->assertSame([1, 0, 1], [$byModel['Nokia 3310']['bought'], $byModel['Nokia 3310']['sold'], $byModel['Nokia 3310']['in_stock']]);

        $byGrade = collect($query('grade')['rows'])->keyBy('name');
        $this->assertSame([1, 1, 1], [$byGrade['فئة A']['in_stock'], $byGrade['فئة B']['sold'], $byGrade['فئة C']['bought']]);

        // Without costs: no purchase prices or profit.
        Sanctum::actingAs($this->staffWith(['used_devices.manage', 'reports.view']));
        $plain = $query('device');
        $this->assertNotContains('purchase_price', array_column($plain['columns'], 'key'));
        $this->assertNotContains('profit', array_column($plain['columns'], 'key'));
    }
}
