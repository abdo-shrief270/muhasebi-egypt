<?php

namespace Tests\Feature\MultiBranch;

use App\Modules\Identity\PermissionResolver;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Notifications\Models\Notification;
use App\Support\Events\EventRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** «التحويلات»: goods move between branches with their stock, serials and cost. */
class TransfersTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private string $second;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->postJson('/api/v1/modules/multi_branch/trial')->assertOk();
        app(PermissionResolver::class)->forget();
        $this->second = $this->postJson('/api/v1/branches', ['name' => 'فرع المعادي'])->assertCreated()->json('data.id');
    }

    private function qty(string $branch, string $variant): int
    {
        return $this->inShop(fn () => app(StockLedger::class)->quantity($branch, $variant));
    }

    public function test_request_ship_receive_moves_stock_at_its_cost(): void
    {
        $created = $this->postJson('/api/v1/transfers', [
            'from_branch_id' => $this->branchId, 'to_branch_id' => $this->second,
            'items' => [['variant_id' => $this->v[0], 'qty' => 10], ['variant_id' => $this->v[1], 'qty' => 2]],
        ])->assertCreated()->json('data');
        $this->assertSame(['TR-00001', 'requested', true], [$created['reference'], $created['status'], $created['can_ship']]);
        $this->assertSame(1, $this->getJson('/api/v1/transfers')->json('meta.to_ship'));

        // Ship less of the cases than asked; nothing leaves before it's shipped.
        $this->assertSame(50, $this->qty($this->branchId, $this->v[0]));
        $lines = collect($created['items'])->keyBy('variant_id');
        $this->postJson("/api/v1/transfers/{$created['id']}/ship", ['lines' => [
            ['item_id' => $lines[$this->v[0]]['id'], 'qty' => 8],
            ['item_id' => $lines[$this->v[1]]['id'], 'qty' => 99],
        ]])->assertUnprocessable()->assertJsonPath('code', 'out_of_stock');
        $shipped = $this->postJson("/api/v1/transfers/{$created['id']}/ship", ['lines' => [
            ['item_id' => $lines[$this->v[0]]['id'], 'qty' => 8],
            ['item_id' => $lines[$this->v[1]]['id'], 'qty' => 2],
        ]])->assertOk()->json('data');
        $this->assertSame(['shipped', 10, 8 * 4000 + 2 * 30000], [$shipped['status'], $shipped['units_shipped'], $shipped['value']]);
        $this->assertSame([42, 0], [$this->qty($this->branchId, $this->v[0]), $this->qty($this->second, $this->v[0])]);
        app(EventRelay::class)->publishPending();
        $this->assertSame('تحويل TR-00001 في الطريق لـ «فرع المعادي»', Notification::withoutTenancy()->latest('created_at')->value('title'));

        // One charger didn't arrive: received short, recorded.
        $this->postJson("/api/v1/transfers/{$created['id']}/receive", ['lines' => [
            ['item_id' => $lines[$this->v[0]]['id'], 'qty' => 9],
        ]])->assertUnprocessable()->assertJsonPath('code', 'transfer_qty_invalid');
        $received = $this->postJson("/api/v1/transfers/{$created['id']}/receive", ['lines' => [
            ['item_id' => $lines[$this->v[0]]['id'], 'qty' => 8],
            ['item_id' => $lines[$this->v[1]]['id'], 'qty' => 1],
        ]])->assertOk()->json('data');
        $this->assertSame(['received', 9], [$received['status'], $received['units_received']]);
        $this->assertSame([8, 1], [$this->qty($this->second, $this->v[0]), $this->qty($this->second, $this->v[1])]);
        $this->assertDatabaseHas('audit_log', ['action' => 'transfers.short']);

        // Received at the cost it left with.
        $this->assertSame(4000, $this->inShop(fn () => app(StockLedger::class)->averageCosts($this->second, [$this->v[0]]))[$this->v[0]]);
        $this->postJson("/api/v1/transfers/{$created['id']}/cancel", ['reason' => 'x'])->assertUnprocessable();
    }

    public function test_ship_now_and_cancel_puts_goods_back(): void
    {
        $t = $this->postJson('/api/v1/transfers', [
            'from_branch_id' => $this->branchId, 'to_branch_id' => $this->second, 'ship_now' => true,
            'items' => [['variant_id' => $this->v[0], 'qty' => 5]],
        ])->assertCreated()->json('data');
        $this->assertSame(['shipped', 45], [$t['status'], $this->qty($this->branchId, $this->v[0])]);

        $this->postJson("/api/v1/transfers/{$t['id']}/cancel", ['reason' => 'غلط'])->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame([50, 0], [$this->qty($this->branchId, $this->v[0]), $this->qty($this->second, $this->v[0])]);

        $this->postJson('/api/v1/transfers', ['from_branch_id' => $this->branchId, 'to_branch_id' => $this->branchId, 'items' => [['variant_id' => $this->v[0], 'qty' => 1]]])
            ->assertUnprocessable()->assertJsonPath('code', 'transfer_same_branch');
    }

    public function test_serials_travel_with_the_units(): void
    {
        $phone = $this->variant('موبايل X', 'موبايلات جديدة', ['barcode' => 'PH-1', 'price_retail' => 900000]);
        $product = $this->getJson('/api/v1/products?q=PH-1')->json('data.0.id');
        $this->patchJson("/api/v1/products/{$product}", ['track_serial' => true])->assertOk();
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $phone, 'qty' => 2, 'unit_cost' => 800000, 'serials' => ['111111111111111', '222222222222222']]]])->assertCreated();

        $this->postJson('/api/v1/transfers', ['from_branch_id' => $this->branchId, 'to_branch_id' => $this->second, 'ship_now' => true, 'items' => [['variant_id' => $phone, 'qty' => 1]]])
            ->assertUnprocessable()->assertJsonPath('code', 'serials_required');
        $t = $this->postJson('/api/v1/transfers', [
            'from_branch_id' => $this->branchId, 'to_branch_id' => $this->second, 'ship_now' => true,
            'items' => [['variant_id' => $phone, 'qty' => 1, 'serials' => ['111111111111111']]],
        ])->assertCreated()->json('data');
        $this->postJson("/api/v1/transfers/{$t['id']}/receive", ['lines' => [['item_id' => $t['items'][0]['id'], 'qty' => 1, 'serials' => ['222222222222222']]]])
            ->assertUnprocessable()->assertJsonPath('code', 'transfer_serial_unknown');
        $this->postJson("/api/v1/transfers/{$t['id']}/receive", ['lines' => [['item_id' => $t['items'][0]['id'], 'qty' => 1]]])->assertOk();

        $where = $this->getJson('/api/v1/inventory/serials?q=111111111111111')->json('data.0');
        $this->assertSame(['in_stock', $this->second], [$where['status'], $where['branch_id']]);
    }

    public function test_only_the_users_branches(): void
    {
        $cashier = $this->staff('cashier');
        $this->actingAs($cashier);
        app(PermissionResolver::class)->forget();
        $this->getJson('/api/v1/transfers')->assertForbidden();
    }
}
