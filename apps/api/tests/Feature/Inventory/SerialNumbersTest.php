<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Models\SerialEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class SerialNumbersTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private string $phone;

    private string $supplierId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openShopWithStock();
        $this->openShift(100000);
        $this->phone = $this->variant('Samsung A15', 'موبايلات جديدة', ['price_retail' => 700000]);
        $product = $this->getJson('/api/v1/products?q=A15')->json('data.0');
        $this->patchJson("/api/v1/products/{$product['id']}", ['track_serial' => true])->assertOk();
        $this->supplierId = $this->postJson('/api/v1/suppliers', ['name' => 'موزع سامسونج'])->assertCreated()->json('data.id');
    }

    private function buy(array $serials, int $qty = 2)
    {
        return $this->postJson('/api/v1/purchases', [
            'supplier_id' => $this->supplierId,
            'invoice_date' => now()->toDateString(),
            'items' => [['variant_id' => $this->phone, 'qty' => $qty, 'unit_cost' => 600000, 'serials' => $serials]],
        ]);
    }

    private function sellPhone(array $serials, int $qty = 1)
    {
        return $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $this->phone, 'qty' => $qty, 'serials' => $serials]],
            'payments' => [['method' => 'cash', 'amount' => 700000 * $qty]],
        ]);
    }

    private function lookup(string $q, bool $inStock = false): array
    {
        return $this->getJson('/api/v1/inventory/serials?'.http_build_query(['q' => $q, 'in_stock' => $inStock ? 1 : 0]))->assertOk()->json('data');
    }

    public function test_a_phone_is_bought_sold_and_returned_by_its_imei(): void
    {
        $this->buy(['35-123456-789012-1'])->assertStatus(422)->assertJsonPath('code', 'serials_required');
        $purchase = $this->buy(['351234567890121', '351234567890139'])->assertCreated()->json('data');
        $this->assertSame(['351234567890121', '351234567890139'], $purchase['items'][0]['serials']);

        $this->sellPhone([])->assertStatus(422)->assertJsonPath('code', 'serials_required');
        $sale = $this->sellPhone(['3512 3456 7890 121'])->assertCreated()->json('data');
        $this->assertSame(['351234567890121'], $sale['items'][0]['serials'], 'normalised');
        $this->sellPhone(['351234567890121'])->assertStatus(422)->assertJsonPath('code', 'serial_not_in_stock');

        // Found by its last digits, with its story.
        [$found] = $this->lookup('890121');
        $this->assertSame(['351234567890121', 'out', 'Samsung A15'], [$found['serial'], $found['status'], $found['variant']['display_name']]);
        $this->assertSame(['purchase', 'sale'], array_column($found['events'], 'type'));
        $this->assertSame($sale['id'], $found['events'][1]['ref_id']);

        // In stock here: only the one not sold.
        $this->assertSame(['351234567890139'], array_column($this->lookup('351234567890139', true), 'serial'));
        $this->assertSame([], $this->lookup('351234567890121', true));

        // The customer brings it back: in stock again, sellable again.
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => true]]])
            ->assertCreated();
        $this->assertSame('in_stock', $this->lookup('351234567890121')[0]['status']);
        $this->sellPhone(['351234567890121'])->assertCreated();
    }

    public function test_the_same_imei_cannot_come_in_twice_or_be_sold_as_another_item(): void
    {
        $this->buy(['111122223333444', '111122223333444'])->assertStatus(422)->assertJsonPath('code', 'serial_duplicate');
        $this->buy(['111122223333444', '555566667777888'])->assertCreated();
        $this->buy(['111122223333444', '999900001111222'])->assertStatus(422)->assertJsonPath('code', 'serial_in_stock');

        // A serial on another product's line is refused.
        $other = $this->variant('Oppo A18', 'موبايلات جديدة', ['price_retail' => 500000]);
        $product = $this->getJson('/api/v1/products?q=Oppo')->json('data.0');
        $this->patchJson("/api/v1/products/{$product['id']}", ['track_serial' => true])->assertOk();
        $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $other, 'qty' => 1, 'serials' => ['111122223333444']]],
            'payments' => [['method' => 'cash', 'amount' => 500000]],
        ])->assertStatus(422)->assertJsonPath('code', 'serial_not_in_stock');

        // Products that don't track serials ignore them.
        $this->postJson('/api/v1/sales', ['items' => [['variant_id' => $this->v[0], 'qty' => 1, 'serials' => ['ABCD1234']]], 'payments' => [['method' => 'cash', 'amount' => 10000]]])
            ->assertCreated()->assertJsonPath('data.items.0.serials', null);
    }

    public function test_stock_from_before_serials_is_sold_with_its_imei(): void
    {
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $this->phone, 'qty' => 3, 'unit_cost' => 600000]]])->assertCreated();

        $this->sellPhone(['867530900000011'])->assertCreated();
        $this->assertSame(['out', ['sale']], [$this->lookup('867530900000011')[0]['status'], array_column($this->lookup('867530900000011')[0]['events'], 'type')]);
    }

    public function test_returns_pick_which_units_and_damaged_ones_stay_out_of_stock(): void
    {
        $this->buy(['123451234512345', '543215432154321'])->assertCreated();
        $sale = $this->sellPhone(['123451234512345', '543215432154321'], 2)->assertCreated()->json('data');
        $itemId = $sale['items'][0]['id'];

        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $itemId, 'qty' => 1, 'restock' => false]]])
            ->assertStatus(422)->assertJsonPath('code', 'serials_required');
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $itemId, 'qty' => 1, 'restock' => false, 'serials' => ['999999999999999']]]])
            ->assertStatus(422)->assertJsonPath('code', 'serials_required');
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $itemId, 'qty' => 1, 'restock' => false, 'serials' => ['543215432154321']]]])
            ->assertCreated();
        $this->assertSame('damaged', $this->lookup('543215432154321')[0]['status']);

        // The last one: no need to say which.
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $itemId, 'qty' => 1, 'restock' => true]]])
            ->assertCreated();
        $this->assertSame(['in_stock', 'damaged'], [$this->lookup('123451234512345')[0]['status'], $this->lookup('543215432154321')[0]['status']]);
    }

    public function test_a_unit_goes_back_to_the_supplier_by_its_imei(): void
    {
        $purchase = $this->buy(['777788889999000', '777788889999011'])->assertCreated()->json('data');
        $itemId = $purchase['items'][0]['id'];

        $this->postJson("/api/v1/purchases/{$purchase['id']}/returns", ['items' => [['purchase_item_id' => $itemId, 'qty' => 1]]])
            ->assertStatus(422)->assertJsonPath('code', 'serials_required');
        $this->sellPhone(['777788889999000'])->assertCreated();
        $this->postJson("/api/v1/purchases/{$purchase['id']}/returns", ['items' => [['purchase_item_id' => $itemId, 'qty' => 1, 'serials' => ['777788889999000']]]])
            ->assertStatus(422)->assertJsonPath('code', 'serial_not_in_stock');
        $this->postJson("/api/v1/purchases/{$purchase['id']}/returns", ['items' => [['purchase_item_id' => $itemId, 'qty' => 1, 'serials' => ['777788889999011']]]])
            ->assertCreated();
        $this->assertSame(['purchase', 'supplier_return'], array_column($this->lookup('777788889999011')[0]['events'], 'type'));

        $this->expectException(LogicException::class);
        $this->inShop(fn () => SerialEvent::query()->first()->delete());
    }

    private function adjust(array $item, string $reason = 'count')
    {
        return $this->postJson('/api/v1/inventory/adjustments', ['reason' => $reason, 'items' => [['variant_id' => $this->phone, ...$item]]]);
    }

    public function test_adjustments_and_counts_name_the_units_that_come_in_or_leave(): void
    {
        $this->buy(['350000000000011', '350000000000022'])->assertCreated();

        // Found one more on the shelf: which one?
        $this->adjust(['delta' => 1], 'correction')->assertStatus(422)->assertJsonPath('code', 'serials_required');
        $this->adjust(['delta' => 1, 'serials' => ['350000000000011']], 'correction')->assertStatus(422)->assertJsonPath('code', 'serial_in_stock');
        $this->adjust(['delta' => 1, 'serials' => ['3500-0000-0000-033']], 'correction')->assertOk()->assertJsonPath('data.changes.0.serials', ['350000000000033']);
        $this->assertSame(['in_stock', ['adjustment']], [$this->lookup('350000000000033')[0]['status'], array_column($this->lookup('350000000000033')[0]['events'], 'type')]);

        // One broke: which one leaves. A sold one can't leave again.
        $this->sellPhone(['350000000000022'])->assertCreated();
        $this->adjust(['delta' => -1], 'damaged')->assertStatus(422)->assertJsonPath('code', 'serials_required');
        $this->adjust(['delta' => -1, 'serials' => ['350000000000022']], 'damaged')->assertStatus(422)->assertJsonPath('code', 'serial_not_in_stock');
        $this->adjust(['delta' => -1, 'serials' => ['350000000000011']], 'damaged')->assertOk();
        $this->assertSame(['out', ['purchase', 'adjustment']], [$this->lookup('350000000000011')[0]['status'], array_column($this->lookup('350000000000011')[0]['events'], 'type')]);

        // A stocktake: one on the books (…033) is missing.
        $this->adjust(['counted' => 0, 'serials' => []])->assertStatus(422)->assertJsonPath('code', 'serials_required');
        $this->adjust(['counted' => 0, 'serials' => ['350000000000033']])->assertOk()->assertJsonPath('data.changes.0.delta', -1);
        $this->assertSame([], $this->lookup('350000000000033', true));
        $this->adjust(['counted' => 0])->assertOk()->assertJsonPath('data.changes', []);
    }

    public function test_opening_stock_records_serials_when_given(): void
    {
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $this->phone, 'qty' => 2, 'unit_cost' => 600000, 'serials' => ['360000000000011']]]])
            ->assertStatus(422)->assertJsonPath('code', 'serials_required');
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $this->phone, 'qty' => 2, 'unit_cost' => 600000, 'serials' => ['360000000000011', '360000000000022']]]])
            ->assertCreated();
        $this->assertSame([['360000000000011'], ['360000000000022']], [array_column($this->lookup('360000000000011', true), 'serial'), array_column($this->lookup('360000000000022', true), 'serial')]);
        $this->assertSame(['opening'], array_column($this->lookup('360000000000011')[0]['events'], 'type'));

        // Products that don't track serials ignore them.
        $other = $this->variant('كابل', 'شواحن', ['price_retail' => 5000]);
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $other, 'qty' => 1, 'unit_cost' => 1000, 'serials' => ['ABCD1234']]]])->assertCreated();
        $this->assertSame([], $this->lookup('ABCD1234'));
    }

    public function test_a_repair_part_is_fitted_and_taken_back_by_its_serial(): void
    {
        $this->buy(['370000000000011', '370000000000022'])->assertCreated();
        $this->sellPhone(['370000000000022'])->assertCreated();
        $ticket = $this->postJson('/api/v1/repairs/tickets', [
            'customer_name' => 'محمود', 'customer_phone' => '01234567890', 'device_name' => 'Samsung A15', 'reported_note' => 'بدل الجهاز',
        ])->assertCreated()->json('data');
        $url = "/api/v1/repairs/tickets/{$ticket['id']}/parts";

        $this->postJson($url, ['variant_id' => $this->phone, 'qty' => 1])->assertStatus(422)->assertJsonPath('code', 'serials_required');
        $this->postJson($url, ['variant_id' => $this->phone, 'qty' => 1, 'serials' => ['370000000000022']])->assertStatus(422)->assertJsonPath('code', 'serial_not_in_stock');
        $part = $this->postJson($url, ['variant_id' => $this->phone, 'qty' => 1, 'serials' => ['3700 0000 0000 011']])->assertOk()->json('data.parts.0');
        $this->assertSame(['370000000000011'], $part['serials']);
        [$found] = $this->lookup('370000000000011');
        $this->assertSame(['out', ['purchase', 'repair_use'], $ticket['id']], [$found['status'], array_column($found['events'], 'type'), $found['events'][1]['ref_id']]);

        // Taken back off the device: in stock again, sellable again.
        $this->deleteJson("{$url}/{$part['id']}")->assertOk()->assertJsonPath('data.parts', []);
        [$found] = $this->lookup('370000000000011');
        $this->assertSame(['in_stock', ['purchase', 'repair_use', 'repair_return']], [$found['status'], array_column($found['events'], 'type')]);
        $this->sellPhone(['370000000000011'])->assertCreated();

        // Parts that don't track serials carry none.
        $this->postJson($url, ['variant_id' => $this->v[1], 'qty' => 1, 'serials' => ['ABCD1234']])->assertOk()->assertJsonPath('data.parts.0.serials', null);
    }

    public function test_who_may_look_up(): void
    {
        $this->getJson('/api/v1/inventory/serials?q=12')->assertOk()->assertJsonPath('data', []);
        $roleId = $this->postJson('/api/v1/roles', ['name' => 'استقبال', 'permissions' => ['repairs.view']])->assertCreated()->json('data.id');
        $userId = $this->postJson('/api/v1/users', ['name' => 'استقبال', 'phone' => '01122223333', 'password' => 'password', 'role_id' => $roleId, 'branch_ids' => [$this->branchId]])
            ->assertCreated()->json('data.id');
        Sanctum::actingAs(User::query()->findOrFail($userId));
        $this->getJson('/api/v1/inventory/serials?q=123456')->assertForbidden();
    }
}
