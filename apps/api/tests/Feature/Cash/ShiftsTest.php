<?php

namespace Tests\Feature\Cash;

use App\Modules\Cash\Models\CashMovement;
use App\Support\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class ShiftsTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
    }

    public function test_selling_needs_an_open_shift(): void
    {
        $this->getJson('/api/v1/cash/current')->assertOk()->assertJsonPath('data', null);
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertStatus(409)->assertJsonPath('code', 'shift_not_open');
        $this->sell([['method' => 'card', 'amount' => 45000]])->assertStatus(409)->assertJsonPath('code', 'shift_not_open');

        $shift = $this->openShift(50000)->json('data');
        $this->assertSame(['SH-00001', true, 50000], [$shift['reference'], $shift['is_open'], $shift['expected']['cash']]);
        $this->postJson('/api/v1/cash/shifts', ['opening_cash' => 0])->assertStatus(409)->assertJsonPath('code', 'shift_already_open');

        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();
    }

    public function test_the_drawer_follows_sales_refunds_and_manual_movements_and_closes_with_the_difference(): void
    {
        $this->openShift(50000);
        // 450 cash with 50 change → +450; 450 split card 200 + cash 250 → card +200, cash +250.
        $sale = $this->sell([['method' => 'cash', 'amount' => 50000]])->assertCreated()->json('data');
        $this->sell([['method' => 'card', 'amount' => 20000], ['method' => 'cash', 'amount' => 25000]])->assertCreated();
        // The first charger comes back, refunded in cash: -450.
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => true]]])
            ->assertCreated();

        $this->postJson('/api/v1/cash/movements', ['type' => 'expense', 'amount' => 3000, 'category' => 'hospitality', 'note' => 'شاي'])->assertCreated();
        $this->postJson('/api/v1/cash/movements', ['type' => 'deposit', 'amount' => 10000, 'note' => 'فكة'])->assertCreated();
        $this->postJson('/api/v1/cash/movements', ['type' => 'withdrawal', 'amount' => 1000000, 'note' => 'للمالك'])
            ->assertUnprocessable()->assertJsonPath('code', 'insufficient_cash');
        $this->postJson('/api/v1/cash/movements', ['type' => 'withdrawal', 'note' => 'x', 'amount' => 20000])->assertCreated();
        $this->postJson('/api/v1/cash/movements', ['type' => 'deposit', 'amount' => 100])->assertUnprocessable()->assertJsonValidationErrors('note');
        $this->postJson('/api/v1/cash/movements', ['type' => 'sale', 'amount' => 100, 'note' => 'x'])->assertUnprocessable();

        $current = $this->getJson('/api/v1/cash/current')->assertOk()->json('data');
        // 500 + 450 + 250 - 450 - 30 + 100 - 200 = 620
        $this->assertSame(62000, $current['expected']['cash']);
        $this->assertSame(20000, $current['expected']['card']);
        $this->assertCount(7, $current['movements']);
        $this->assertSame(['expense', -3000, 'بوفيه وضيافة'], [$current['movements'][2]['type'], $current['movements'][2]['amount'], $current['movements'][2]['category_label']]);

        // Counted 600 in cash (20 short); card not counted = as expected.
        $closed = $this->postJson("/api/v1/cash/shifts/{$current['id']}/close", ['counted' => ['cash' => 60000]])->assertOk()->json('data');
        $this->assertSame([false, -2000, 20000], [$closed['is_open'], $closed['cash_difference'], $closed['counted']['card']]);
        $this->assertSame(1, AuditEntry::query()->where('action', 'cash.shift_closed')->count());
        $this->postJson("/api/v1/cash/shifts/{$current['id']}/close", ['counted' => ['cash' => 1]])->assertStatus(409)->assertJsonPath('code', 'shift_closed');

        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertStatus(409);
        $this->assertSame(1, count($this->getJson('/api/v1/cash/shifts')->json('data')));
    }

    public function test_permissions_and_managers_closing_a_cashiers_shift(): void
    {
        $cashier = $this->staff('cashier');
        $manager = $this->staff('manager');

        Sanctum::actingAs($cashier);
        $shift = $this->openShift(10000)->json('data');
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();
        $this->postJson('/api/v1/cash/movements', ['type' => 'expense', 'amount' => 1000, 'category' => 'transport'])->assertCreated();
        $this->postJson('/api/v1/cash/movements', ['type' => 'withdrawal', 'amount' => 1000, 'note' => 'x'])->assertForbidden();
        $this->getJson('/api/v1/cash/summary')->assertForbidden();

        Sanctum::actingAs($this->owner);
        $this->openShift();
        $this->assertCount(2, $this->getJson('/api/v1/cash/shifts')->json('data'), 'the owner sees every shift');
        $summary = $this->getJson('/api/v1/cash/summary')->assertOk()->json('data');
        $this->assertSame([10000 + 45000 - 1000, 1000], [$summary['in_drawers'], $summary['expenses_today']]);

        Sanctum::actingAs($cashier);
        $this->assertCount(1, $this->getJson('/api/v1/cash/shifts')->json('data'), 'a cashier sees their own');

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/cash/shifts/{$shift['id']}/close", ['counted' => ['cash' => 54000]])->assertOk()
            ->assertJsonPath('data.cash_difference', 0)
            ->assertJsonPath('data.closed_by_name', 'manager');
    }

    public function test_movements_are_append_only(): void
    {
        $this->openShift();
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();

        $movement = $this->inShop(fn () => CashMovement::query()->firstOrFail());
        $this->expectException(LogicException::class);
        $movement->update(['amount' => 1]);
    }
}
