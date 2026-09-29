<?php

namespace Tests\Feature\Inventory;

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Support\Audit\AuditEntry;
use App\Support\Events\EventRelay;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class StockTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $owner;

    private string $branchId;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->newShop();
        $this->branchId = $this->inShop(fn () => Branch::query()->value('id'));
        Sanctum::actingAs($this->owner);
    }

    private function newShop(): User
    {
        $owner = $this->registerShop(ShopType::AccessoriesAndRepair);
        app(EventRelay::class)->publishPending();

        return $owner;
    }

    private function inShop(callable $callback, ?User $user = null): mixed
    {
        return app(CurrentTenant::class)->runAs(($user ?? $this->owner)->tenant_id, $callback);
    }

    /** Creates a product and returns its variant ids. */
    private function variants(string $name = 'سكرينة 9D', array $variants = [['name' => 'شفاف', 'price_retail' => 15000, 'min_stock' => 3]]): array
    {
        return array_column($this->postJson('/api/v1/products', [
            'name' => $name,
            'category_id' => $this->inShop(fn () => Category::query()->where('name', 'سكرينات حماية')->value('id')),
            'variants' => $variants,
        ])->assertCreated()->json('data.variants'), 'id');
    }

    private function ledger(): StockLedger
    {
        return app(StockLedger::class);
    }

    private function ref(MovementType $type = MovementType::Purchase): StockReference
    {
        return new StockReference($type);
    }

    public function test_weighted_average_cost_and_fifo_issue(): void
    {
        [$v] = $this->variants();

        $this->inShop(function () use ($v): void {
            $this->ledger()->receive($this->branchId, $v, 10, 10000, $this->ref());
            $this->ledger()->receive($this->branchId, $v, 10, 20000, $this->ref());

            $issue = $this->ledger()->issue($this->branchId, $v, 15, $this->ref(MovementType::Sale));

            $this->assertSame([[10, 10000], [5, 20000]], array_map(fn ($p) => [$p->qty, $p->unitCost], $issue->portions));
            $this->assertSame(200000, $issue->totalCost());
            $this->assertSame(13333, $issue->unitCost());
            $this->assertSame(5, $issue->balanceAfter);
            $this->assertSame(5, $this->ledger()->quantity($this->branchId, $v));
        });

        $item = $this->getJson("/api/v1/inventory/variants/{$v}/movements")->assertOk()->json('data');
        $this->assertSame(15000, $item['item']['avg_cost'], 'issues do not change the average');
        $this->assertSame(75000, $item['item']['value']);
        $this->assertSame([-5, -10, 10, 10], array_column($item['movements'], 'qty'), 'newest first; one line per lot taken from');
        $this->assertSame([5, 10, 20, 10], array_column($item['movements'], 'balance_after'));
        $this->assertSame('بيع', $item['movements'][0]['type_label']);
    }

    public function test_selling_below_zero_is_allowed_and_the_next_receipt_covers_it(): void
    {
        [$v] = $this->variants();

        $this->inShop(function () use ($v): void {
            $this->ledger()->receive($this->branchId, $v, 2, 5000, $this->ref());
            $issue = $this->ledger()->issue($this->branchId, $v, 5, $this->ref(MovementType::Sale));

            $this->assertSame([[2, 5000], [3, 5000]], array_map(fn ($p) => [$p->qty, $p->unitCost], $issue->portions), 'the missing part at the average cost');
            $this->assertNull($issue->portions[1]->lotId);
            $this->assertSame(-3, $issue->balanceAfter);

            $lotId = $this->ledger()->receive($this->branchId, $v, 10, 8000, $this->ref());
            $this->assertSame(7, StockLot::query()->findOrFail($lotId)->qty_remaining, '3 of the 10 cover what was already sold');
            $this->assertSame(7, $this->ledger()->quantity($this->branchId, $v));
        });

        $this->assertSame(8000, $this->getJson("/api/v1/inventory/variants/{$v}/movements")->json('data.item.avg_cost'), 'restarts from the new cost after a negative balance');
    }

    public function test_opening_stock_once_per_branch(): void
    {
        [$a, $b] = $this->variants('جراب', [['name' => 'أسود', 'price_retail' => 100], ['name' => 'أحمر', 'price_retail' => 100]]);

        $this->postJson('/api/v1/inventory/opening', ['items' => [
            ['variant_id' => $a, 'qty' => 12, 'unit_cost' => 4000],
            ['variant_id' => $b, 'qty' => 3, 'unit_cost' => 4500],
        ]])->assertCreated()->assertJsonPath('data.units', 15);

        $this->assertSame(12, $this->inShop(fn () => $this->ledger()->quantity($this->branchId, $a)));
        $this->assertSame(1, AuditEntry::query()->where('action', 'inventory.opening')->count());

        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $a, 'qty' => 1]]])
            ->assertUnprocessable()->assertJsonPath('code', 'opening_after_movements');
    }

    public function test_stocktake_and_signed_adjustments(): void
    {
        [$a, $b, $c] = $this->variants('جراب', [['name' => 'أسود', 'price_retail' => 100], ['name' => 'أحمر', 'price_retail' => 100], ['name' => 'أزرق', 'price_retail' => 100]]);
        $this->postJson('/api/v1/inventory/opening', ['items' => [
            ['variant_id' => $a, 'qty' => 10, 'unit_cost' => 4000],
            ['variant_id' => $b, 'qty' => 10, 'unit_cost' => 4000],
        ]])->assertCreated();

        $changes = $this->postJson('/api/v1/inventory/adjustments', [
            'reason' => 'count',
            'note' => 'جرد آخر الشهر',
            'items' => [
                ['variant_id' => $a, 'counted' => 7],           // 3 missing
                ['variant_id' => $b, 'counted' => 10],          // matches: no movement
                ['variant_id' => $c, 'counted' => 4],           // found 4 never recorded
            ],
        ])->assertOk()->json('data.changes');

        $this->assertSame([[10, 7, -3], [0, 4, 4]], array_map(fn ($c) => [$c['before'], $c['after'], $c['delta']], $changes));

        $this->postJson('/api/v1/inventory/adjustments', ['reason' => 'damaged', 'items' => [['variant_id' => $a, 'delta' => -2]]])
            ->assertOk()->assertJsonPath('data.changes.0.after', 5);

        $history = $this->getJson("/api/v1/inventory/variants/{$a}/movements")->json('data.movements');
        $this->assertSame(['تالف', 'جرد'], array_column(array_slice($history, 0, 2), 'reason_label'));
        $this->assertSame('جرد آخر الشهر', $history[1]['note']);
        $this->assertSame($this->owner->name, $history[0]['user_name']);
        $this->assertSame(2, AuditEntry::query()->where('action', 'inventory.adjusted')->count());
    }

    public function test_adjustment_validation(): void
    {
        [$a] = $this->variants();

        $this->postJson('/api/v1/inventory/adjustments', ['reason' => 'count', 'items' => [['variant_id' => $a]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.counted');
        $this->postJson('/api/v1/inventory/adjustments', ['reason' => 'count', 'items' => [['variant_id' => $a, 'counted' => 1, 'delta' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.counted');
        $this->postJson('/api/v1/inventory/adjustments', ['reason' => 'nope', 'items' => [['variant_id' => $a, 'counted' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson('/api/v1/inventory/adjustments', ['reason' => 'count', 'items' => [['variant_id' => $a, 'counted' => -1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.0.counted');
    }

    public function test_list_filters_and_summary(): void
    {
        [$plenty, $low, $none] = $this->variants('جراب', [
            ['name' => 'كتير', 'price_retail' => 100, 'min_stock' => 2],
            ['name' => 'قليل', 'price_retail' => 100, 'min_stock' => 5],
            ['name' => 'خلصان', 'price_retail' => 100, 'min_stock' => 1],
        ]);
        $this->postJson('/api/v1/inventory/opening', ['items' => [
            ['variant_id' => $plenty, 'qty' => 20, 'unit_cost' => 1000],
            ['variant_id' => $low, 'qty' => 4, 'unit_cost' => 2000],
        ]])->assertCreated();

        $names = fn (string $status) => array_column($this->getJson("/api/v1/inventory?status={$status}")->assertOk()->json('data'), 'variant_name');

        $this->assertSame(['كتير', 'قليل', 'خلصان'], $names('all'));
        $this->assertSame(['كتير', 'قليل'], $names('in'));
        $this->assertSame(['خلصان'], $names('out'));
        $this->assertSame(['قليل', 'خلصان'], $names('low'));

        $rows = collect($this->getJson('/api/v1/inventory')->json('data'))->keyBy('variant_name');
        $this->assertSame(['ok', 'low', 'out'], [$rows['كتير']['status'], $rows['قليل']['status'], $rows['خلصان']['status']]);

        $this->getJson('/api/v1/inventory/summary')->assertOk()->assertJsonPath('data', [
            'variants' => 3, 'in_stock' => 2, 'out_of_stock' => 1, 'low' => 2, 'units' => 24, 'value' => 20 * 1000 + 4 * 2000,
        ]);

        $this->assertSame(['قليل'], array_column($this->getJson('/api/v1/inventory?q='.urlencode('جراب قليل'))->json('data'), 'variant_name'));
    }

    public function test_cashier_sees_quantities_but_not_costs_and_cannot_adjust(): void
    {
        [$v] = $this->variants();
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $v, 'qty' => 5, 'unit_cost' => 1000]]])->assertCreated();

        $cashierId = $this->postJson('/api/v1/users', [
            'name' => 'كاشير',
            'phone' => '01177778888',
            'password' => 'password',
            'role_id' => $this->inShop(fn () => Role::query()->where('key', 'cashier')->value('id')),
            'branch_ids' => [$this->branchId],
        ])->assertCreated()->json('data.id');
        Sanctum::actingAs(User::query()->findOrFail($cashierId));

        $row = $this->getJson('/api/v1/inventory')->assertOk()->json('data.0');
        $this->assertSame([5, null, null], [$row['qty'], $row['avg_cost'], $row['value']]);
        $this->assertNull($this->getJson('/api/v1/inventory/summary')->json('data.value'));
        $this->assertNull($this->getJson("/api/v1/inventory/variants/{$v}/movements")->json('data.movements.0.unit_cost'));
        $this->postJson('/api/v1/inventory/adjustments', ['reason' => 'count', 'items' => [['variant_id' => $v, 'counted' => 1]]])->assertForbidden();
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $v, 'qty' => 1]]])->assertForbidden();
    }

    public function test_each_branch_has_its_own_stock(): void
    {
        [$v] = $this->variants();
        $second = $this->inShop(fn () => Branch::create(['name' => 'فرع تاني', 'invoice_prefix' => 'B2']));

        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $v, 'qty' => 5, 'unit_cost' => 1000]]])->assertCreated();
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $v, 'qty' => 2, 'unit_cost' => 1000]]], ['X-Branch-Id' => $second->id])
            ->assertCreated();

        $this->assertSame(5, $this->getJson('/api/v1/inventory', ['X-Branch-Id' => $this->branchId])->json('data.0.qty'));
        $this->assertSame(2, $this->getJson('/api/v1/inventory', ['X-Branch-Id' => $second->id])->json('data.0.qty'));
    }

    public function test_a_shop_cannot_touch_another_shops_stock(): void
    {
        [$v] = $this->variants();
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $v, 'qty' => 5, 'unit_cost' => 1000]]])->assertCreated();

        Sanctum::actingAs($this->newShop());

        $this->getJson('/api/v1/inventory')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/inventory/variants/{$v}/movements")->assertNotFound();
        $this->postJson('/api/v1/inventory/adjustments', ['reason' => 'count', 'items' => [['variant_id' => $v, 'counted' => 0]]])
            ->assertNotFound()->assertJsonPath('code', 'variant_not_found');
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $v, 'qty' => 1]]])->assertNotFound();
    }

    public function test_the_ledger_is_append_only(): void
    {
        [$v] = $this->variants();
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $v, 'qty' => 5, 'unit_cost' => 1000]]])->assertCreated();

        $movement = $this->inShop(fn () => StockMovement::query()->firstOrFail());

        $this->expectException(LogicException::class);
        $movement->update(['qty' => 500]);
    }

    public function test_a_variant_with_stock_history_cannot_be_removed(): void
    {
        $product = $this->postJson('/api/v1/products', [
            'name' => 'جراب',
            'category_id' => $this->inShop(fn () => Category::query()->where('name', 'جرابات')->value('id')),
            'variants' => [['name' => 'أسود', 'price_retail' => 100], ['name' => 'أحمر', 'price_retail' => 100]],
        ])->json('data');
        [$black, $red] = $product['variants'];
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $black['id'], 'qty' => 1, 'unit_cost' => 1]]])->assertCreated();

        $this->patchJson("/api/v1/products/{$product['id']}", ['variants' => [['id' => $red['id'], 'price_retail' => 100]]])
            ->assertUnprocessable()->assertJsonPath('code', 'variant_has_stock_history');

        // Removing the one without history is fine.
        $this->patchJson("/api/v1/products/{$product['id']}", ['variants' => [['id' => $black['id'], 'price_retail' => 100]]])->assertOk();
    }
}
