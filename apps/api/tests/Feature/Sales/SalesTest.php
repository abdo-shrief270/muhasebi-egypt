<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Sales\Events\SaleCompleted;
use App\Support\Audit\AuditEntry;
use App\Support\Events\EventRelay;
use App\Support\Events\StoredEvent;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class SalesTest extends TestCase
{
    use CreatesShops, RefreshDatabase;

    private User $owner;

    private string $branchId;

    /** @var array{0: string, 1: string} case (100 ج), charger (450 ج) */
    private array $v;

    protected function setUp(): void
    {
        parent::setUp();

        $this->owner = $this->registerShop(ShopType::AccessoriesAndRepair);
        app(EventRelay::class)->publishPending();
        $this->branchId = $this->inShop(fn () => Branch::query()->value('id'));
        Sanctum::actingAs($this->owner);

        $this->v = [
            $this->variant('جراب', 'جرابات', ['barcode' => 'CASE-1', 'price_retail' => 10000, 'price_wholesale' => 7000]),
            $this->variant('شاحن 20W', 'شواحن', ['barcode' => 'CHG-1', 'price_retail' => 45000]),
        ];
        // Stock in at known costs: 10 cases at 40 ج, 5 chargers at 300 ج.
        $this->postJson('/api/v1/inventory/opening', ['items' => [
            ['variant_id' => $this->v[0], 'qty' => 10, 'unit_cost' => 4000],
            ['variant_id' => $this->v[1], 'qty' => 5, 'unit_cost' => 30000],
        ]])->assertCreated();
    }

    private function inShop(callable $callback): mixed
    {
        return app(CurrentTenant::class)->runAs($this->owner->tenant_id, $callback);
    }

    private function variant(string $name, string $category, array $data): string
    {
        return $this->postJson('/api/v1/products', [
            'name' => $name,
            'category_id' => $this->inShop(fn () => Category::query()->where('name', $category)->value('id')),
            'variants' => [$data],
        ])->assertCreated()->json('data.variants.0.id');
    }

    private function sell(array $overrides = [])
    {
        return $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $this->v[0], 'qty' => 2], ['variant_id' => $this->v[1], 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 70000]],
            ...$overrides,
        ]);
    }

    private function stock(string $variantId): int
    {
        return $this->inShop(fn () => app(StockLedger::class)->quantity($this->branchId, $variantId));
    }

    private function staff(string $role): User
    {
        $id = $this->postJson('/api/v1/users', [
            'name' => $role,
            'phone' => '011'.random_int(10000000, 99999999),
            'password' => 'password',
            'role_id' => $this->inShop(fn () => Role::query()->where('key', $role)->value('id')),
            'branch_ids' => [$this->branchId],
        ])->assertCreated()->json('data.id');

        return User::query()->findOrFail($id);
    }

    public function test_a_cash_sale_with_change_takes_stock_out_and_records_cost(): void
    {
        $sale = $this->sell()->assertCreated()->json('data');

        $this->assertSame(['INV-000001', 'completed'], [$sale['reference'], $sale['status']]);
        $this->assertSame([65000, 65000, 70000, 5000], [$sale['subtotal'], $sale['total'], $sale['paid'], $sale['change']]);
        $this->assertSame(['جراب', 'شاحن 20W'], array_column($sale['items'], 'name'));
        $this->assertSame(2 * 4000 + 30000, $sale['cost_total'], 'FIFO cost of what left');
        $this->assertSame(65000 - 38000, $sale['profit']);
        $this->assertSame([8, 4], [$this->stock($this->v[0]), $this->stock($this->v[1])]);
        $this->assertSame(32, strlen($sale['public_token']));

        $event = StoredEvent::query()->where('name', SaleCompleted::NAME)->sole()->toDomainEvent();
        $this->assertSame(['cash' => 70000], $event->payments);
        $this->assertSame(5000, $event->change);
    }

    public function test_prices_come_from_the_catalog_not_the_client(): void
    {
        $sale = $this->sell(['items' => [['variant_id' => $this->v[0], 'qty' => 1, 'unit_price' => 1]], 'payments' => [['method' => 'cash', 'amount' => 10000]]])
            ->assertCreated()->json('data');

        $this->assertSame(10000, $sale['items'][0]['unit_price']);
    }

    public function test_split_payment_and_payment_rules(): void
    {
        $sale = $this->sell(['payments' => [['method' => 'card', 'amount' => 40000, 'reference' => 'AUTH-9'], ['method' => 'cash', 'amount' => 30000]]])
            ->assertCreated()->json('data');
        $this->assertSame([['card', 40000], ['cash', 30000]], array_map(fn ($p) => [$p['method'], $p['amount']], $sale['payments']));
        $this->assertSame(5000, $sale['change']);

        $this->sell(['payments' => [['method' => 'cash', 'amount' => 60000]]])
            ->assertUnprocessable()->assertJsonPath('code', 'underpaid')->assertJsonPath('missing', 5000);
        $this->sell(['payments' => [['method' => 'card', 'amount' => 70000]]])
            ->assertUnprocessable()->assertJsonPath('code', 'overpaid_non_cash');
        $this->sell(['items' => [['variant_id' => $this->v[0], 'qty' => 1], ['variant_id' => $this->v[0], 'qty' => 1]]])
            ->assertUnprocessable()->assertJsonValidationErrors('items.1.variant_id');
    }

    public function test_discounts_and_price_levels_need_permission(): void
    {
        $sale = $this->sell([
            'items' => [['variant_id' => $this->v[0], 'qty' => 2, 'discount' => 2000], ['variant_id' => $this->v[1], 'qty' => 1]],
            'discount' => 3000,
            'payments' => [['method' => 'cash', 'amount' => 60000]],
        ])->assertCreated()->json('data');
        $this->assertSame([63000, 3000, 60000], [$sale['subtotal'], $sale['discount'], $sale['total']]);
        $this->assertSame(1, AuditEntry::query()->where('action', 'sales.discounted')->count());

        $wholesale = $this->sell(['price_level' => 'wholesale', 'items' => [['variant_id' => $this->v[0], 'qty' => 1], ['variant_id' => $this->v[1], 'qty' => 1]], 'payments' => [['method' => 'cash', 'amount' => 52000]]])
            ->assertCreated()->json('data');
        $this->assertSame([7000, 45000], array_column($wholesale['items'], 'unit_price'), 'no wholesale price falls back to retail');

        $this->sell(['discount' => 100000])->assertUnprocessable()->assertJsonPath('code', 'discount_too_large');

        Sanctum::actingAs($this->staff('cashier'));
        $this->sell()->assertCreated();
        $this->sell(['discount' => 1000])->assertForbidden()->assertJsonPath('code', 'discount_not_allowed');
        $this->sell(['price_level' => 'wholesale'])->assertForbidden();
    }

    public function test_the_same_sale_id_is_saved_once(): void
    {
        $id = (string) Str::uuid7();

        $first = $this->sell(['id' => $id])->assertCreated()->json('data');
        $second = $this->sell(['id' => $id])->assertCreated()->json('data');

        $this->assertSame($first['number'], $second['number']);
        $this->assertSame(8, $this->stock($this->v[0]), 'stock left once');
    }

    public function test_selling_more_than_in_stock_is_allowed_below_zero(): void
    {
        $this->sell(['items' => [['variant_id' => $this->v[1], 'qty' => 7]], 'payments' => [['method' => 'cash', 'amount' => 315000]]])->assertCreated();

        $this->assertSame(-2, $this->stock($this->v[1]));
    }

    public function test_returns_refund_what_was_paid_and_restock_sound_items(): void
    {
        $sale = $this->sell([
            'items' => [['variant_id' => $this->v[0], 'qty' => 2], ['variant_id' => $this->v[1], 'qty' => 1]],
            'discount' => 6500, // 10% off the invoice
            'payments' => [['method' => 'cash', 'amount' => 58500]],
        ])->json('data');
        [$case, $charger] = $sale['items'];

        $after = $this->postJson("/api/v1/sales/{$sale['id']}/returns", [
            'refund_method' => 'cash',
            'reason' => 'مقاس غلط',
            'items' => [
                ['sale_item_id' => $case['id'], 'qty' => 1, 'restock' => true],
                ['sale_item_id' => $charger['id'], 'qty' => 1, 'restock' => false],
            ],
        ])->assertCreated()->json('data');

        $this->assertSame(9000 + 40500, $after['refunded'], 'each unit refunded after its share of the 10% discount');
        $this->assertSame('partially_refunded', $after['status']);
        $this->assertSame([9, 4], [$this->stock($this->v[0]), $this->stock($this->v[1])], 'only the sound case goes back');
        $this->assertSame('RET-00001', $after['returns'][0]['reference']);
        // Revenue 58500 - 49500 = 9000; cost 38000 - 4000 (restocked case) = 34000.
        $this->assertSame(9000 - 34000, $after['profit']);

        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $case['id'], 'qty' => 2, 'restock' => true]]])
            ->assertUnprocessable()->assertJsonPath('code', 'return_exceeds_sale')->assertJsonPath('max', 1);

        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $case['id'], 'qty' => 1, 'restock' => true]]])
            ->assertCreated()->assertJsonPath('data.status', 'refunded');

        Sanctum::actingAs($this->staff('cashier'));
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $case['id'], 'qty' => 1, 'restock' => true]]])
            ->assertForbidden();
    }

    public function test_the_public_receipt_shows_the_sale_without_login_or_costs(): void
    {
        $sale = $this->sell(['customer_name' => 'محمد'])->json('data');

        $this->app['auth']->forgetGuards();
        $receipt = $this->getJson("/api/v1/public/receipts/{$sale['public_token']}")->assertOk()->json('data');

        $this->assertSame(['INV-000001', 'محمد', 65000], [$receipt['reference'], $receipt['customer_name'], $receipt['total']]);
        $this->assertSame('محل 1', $receipt['shop']['name']);
        $this->assertArrayNotHasKey('cost_total', $receipt);
        $this->assertArrayNotHasKey('unit_cost', $receipt['items'][0]);
        $this->getJson('/api/v1/public/receipts/'.str_repeat('a', 32))->assertNotFound();
    }

    public function test_list_and_costs_follow_permissions(): void
    {
        $this->sell(['customer_name' => 'سارة'])->assertCreated();
        $this->sell()->assertCreated();

        $this->assertSame([2, 1], array_column($this->getJson('/api/v1/sales')->assertOk()->json('data'), 'number'));
        $this->assertSame([1], array_column($this->getJson('/api/v1/sales?q='.urlencode('سارة'))->json('data'), 'number'));

        Sanctum::actingAs($this->staff('cashier'));
        $row = $this->getJson('/api/v1/sales')->assertOk()->json('data.0');
        $this->assertArrayNotHasKey('cost_total', $row);
        $this->assertArrayNotHasKey('profit', $row);
        $this->getJson('/api/v1/sales/stats')->assertForbidden();
    }

    public function test_dashboard_stats(): void
    {
        $this->sell()->assertCreated();                                                                               // 650, cost 380
        $this->sell(['items' => [['variant_id' => $this->v[0], 'qty' => 1]], 'payments' => [['method' => 'card', 'amount' => 10000]]])->assertCreated(); // 100, cost 40

        $stats = $this->getJson('/api/v1/sales/stats?days=7')->assertOk()->json('data');

        $this->assertCount(7, $stats['series']);
        $this->assertSame(['sales' => 2, 'revenue' => 75000, 'profit' => 75000 - 42000, 'average' => 37500, 'revenue_yesterday' => 0], $stats['today']);
        $this->assertSame('شاحن 20W', $stats['top_items'][0]['name']);
        $this->assertSame([['method' => 'cash', 'label' => 'كاش', 'amount' => 65000], ['method' => 'card', 'label' => 'فيزا', 'amount' => 10000]], $stats['payments'], 'change comes off cash');
    }

    public function test_pos_items_show_stock_and_put_an_exact_barcode_first(): void
    {
        $items = $this->getJson('/api/v1/pos/items?q=CHG-1')->assertOk()->json('data');

        $this->assertSame(['شاحن 20W', 5, true], [$items[0]['display_name'], $items[0]['qty'], $items[0]['exact_barcode']]);
    }

    public function test_another_shop_cannot_see_or_sell_my_things(): void
    {
        $sale = $this->sell()->json('data');

        Sanctum::actingAs($this->registerShop());
        $this->getJson("/api/v1/sales/{$sale['id']}")->assertNotFound();
        $this->getJson('/api/v1/sales')->assertOk()->assertJsonCount(0, 'data');
        $this->sell()->assertNotFound()->assertJsonPath('code', 'variant_not_found');
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => true]]])
            ->assertNotFound();
    }
}
