<?php

namespace Tests\Feature\Suppliers;

use App\Modules\Catalog\Models\Category;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\Role;
use App\Modules\Identity\Models\User;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Suppliers\Events\PurchaseReceived;
use App\Modules\Suppliers\Events\SupplierPaid;
use App\Modules\Suppliers\Models\SupplierTransaction;
use App\Support\Audit\AuditEntry;
use App\Support\Events\EventRelay;
use App\Support\Events\StoredEvent;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\CreatesShops;
use Tests\TestCase;

class PurchasesTest extends TestCase
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

    /** @return list<string> variant ids */
    private function variants(array $variants = [['name' => 'أسود', 'barcode' => '111', 'price_retail' => 20000], ['name' => 'أحمر', 'barcode' => '222', 'price_retail' => 20000]]): array
    {
        return array_column($this->postJson('/api/v1/products', [
            'name' => 'جراب',
            'category_id' => $this->inShop(fn () => Category::query()->where('name', 'جرابات')->value('id')),
            'variants' => $variants,
        ])->assertCreated()->json('data.variants'), 'id');
    }

    private function supplier(array $overrides = []): array
    {
        return $this->postJson('/api/v1/suppliers', ['name' => 'مورد الجرابات', 'phone' => '01012345678', ...$overrides])->assertCreated()->json('data');
    }

    private function purchase(string $supplierId, array $items, array $overrides = []): array
    {
        return $this->postJson('/api/v1/purchases', [
            'supplier_id' => $supplierId,
            'invoice_date' => now()->toDateString(),
            'items' => $items,
            ...$overrides,
        ])->assertCreated()->json('data');
    }

    private function stock(string $variantId): int
    {
        return $this->inShop(fn () => app(StockLedger::class)->quantity($this->branchId, $variantId));
    }

    private function balance(string $supplierId): int
    {
        return $this->getJson("/api/v1/suppliers/{$supplierId}")->assertOk()->json('data.balance');
    }

    public function test_a_supplier_with_an_opening_balance(): void
    {
        $supplier = $this->supplier(['opening_balance' => 150000]);

        $this->assertSame(150000, $supplier['balance']);
        $statement = $this->getJson("/api/v1/suppliers/{$supplier['id']}/statement")->assertOk()->json('data');
        $this->assertSame([['opening', 150000, 150000]], array_map(fn ($t) => [$t['type'], $t['amount'], $t['balance_after']], $statement));

        $this->postJson('/api/v1/suppliers', ['name' => 'مورد الجرابات'])->assertUnprocessable()->assertJsonValidationErrors('name');
        $this->patchJson("/api/v1/suppliers/{$supplier['id']}", ['opening_balance' => 5])->assertUnprocessable();
    }

    public function test_a_purchase_brings_stock_in_and_charges_the_supplier(): void
    {
        [$black, $red] = $this->variants();
        $supplier = $this->supplier(['opening_balance' => 100000]);

        $purchase = $this->purchase($supplier['id'], [
            ['variant_id' => $black, 'qty' => 10, 'unit_cost' => 10000],
            ['variant_id' => $red, 'qty' => 5, 'unit_cost' => 20000],
        ], ['discount' => 20000, 'paid' => 50000, 'payment_method' => 'cash', 'supplier_invoice_no' => 'INV-77']);

        $this->assertSame([1, 'PUR-00001'], [$purchase['number'], $purchase['reference']]);
        $this->assertSame([200000, 20000, 180000, 50000], [$purchase['subtotal'], $purchase['discount'], $purchase['total'], $purchase['paid']]);
        $this->assertSame(['جراب — أسود', 'جراب — أحمر'], array_column($purchase['items'], 'name'));
        $this->assertSame([9000, 18000], array_column($purchase['items'], 'net_unit_cost'), 'the 10% discount lowers each unit cost');

        $this->assertSame([10, 5], [$this->stock($black), $this->stock($red)]);
        $this->assertSame(100000 + 180000 - 50000, $this->balance($supplier['id']));

        $statement = $this->getJson("/api/v1/suppliers/{$supplier['id']}/statement")->json('data');
        $this->assertSame([['payment', -50000, 230000, 'cash'], ['purchase', 180000, 280000, null]], array_map(
            fn ($t) => [$t['type'], $t['amount'], $t['balance_after'], $t['payment_method']],
            array_slice($statement, 0, 2),
        ));

        $movement = $this->getJson("/api/v1/inventory/variants/{$black}/movements")->json('data');
        $this->assertSame(['purchase', 9000], [$movement['movements'][0]['type'], $movement['movements'][0]['unit_cost']]);
        $this->assertSame(9000, $movement['item']['avg_cost']);

        $this->assertSame(2, $this->purchase($supplier['id'], [['variant_id' => $black, 'qty' => 1, 'unit_cost' => 1]])['number'], 'numbers run per shop');
        $this->assertSame(2, AuditEntry::query()->where('action', 'purchases.created')->count());
    }

    public function test_a_cost_increase_is_flagged(): void
    {
        [$black] = $this->variants();
        $supplier = $this->supplier();
        $this->purchase($supplier['id'], [['variant_id' => $black, 'qty' => 10, 'unit_cost' => 10000]]);

        $second = $this->purchase($supplier['id'], [['variant_id' => $black, 'qty' => 10, 'unit_cost' => 12000]]);

        $this->assertTrue($second['items'][0]['cost_increased']);
        $this->assertSame(10000, $second['items'][0]['previous_cost']);

        $event = StoredEvent::query()->where('name', PurchaseReceived::NAME)->latest('occurred_at')->firstOrFail()->toDomainEvent();
        $this->assertSame([['variant_id' => $black, 'previous_cost' => 10000, 'new_cost' => 12000]], $event->costIncreases);
    }

    public function test_purchase_rules(): void
    {
        [$black] = $this->variants();
        $supplier = $this->supplier();
        $line = [['variant_id' => $black, 'qty' => 2, 'unit_cost' => 1000]];

        $post = fn (array $extra) => $this->postJson('/api/v1/purchases', ['supplier_id' => $supplier['id'], 'invoice_date' => now()->toDateString(), 'items' => $line, ...$extra]);

        $post(['paid' => 5000, 'payment_method' => 'cash'])->assertUnprocessable()->assertJsonPath('code', 'overpaid');
        $post(['discount' => 5000])->assertUnprocessable()->assertJsonPath('code', 'discount_too_large');
        $post(['paid' => 1000])->assertUnprocessable()->assertJsonPath('code', 'payment_method_required');
        $post(['items' => [...$line, ...$line]])->assertUnprocessable()->assertJsonValidationErrors('items.1.variant_id');
        $post(['invoice_date' => now()->addDay()->toDateString()])->assertUnprocessable()->assertJsonValidationErrors('invoice_date');
        $post(['items' => [['variant_id' => '01900000-0000-7000-8000-000000000000', 'qty' => 1, 'unit_cost' => 1]]])->assertNotFound()->assertJsonPath('code', 'variant_not_found');

        $this->patchJson("/api/v1/suppliers/{$supplier['id']}", ['is_active' => false])->assertOk();
        $post([])->assertUnprocessable()->assertJsonPath('code', 'supplier_inactive');
        $this->assertSame(0, $this->stock($black), 'nothing posted');
    }

    public function test_payments_reduce_the_balance_and_may_go_beyond_it(): void
    {
        $supplier = $this->supplier(['opening_balance' => 30000]);

        $this->postJson("/api/v1/suppliers/{$supplier['id']}/payments", ['amount' => 50000, 'payment_method' => 'instapay', 'note' => 'مقدم'])
            ->assertCreated()->assertJsonPath('data.balance_after', -20000)->assertJsonPath('data.payment_method_label', 'InstaPay');

        $this->assertSame(-20000, $this->balance($supplier['id']), 'negative: the supplier owes the shop');
        $this->assertTrue(StoredEvent::query()->where('name', SupplierPaid::NAME)->exists());
        $this->postJson("/api/v1/suppliers/{$supplier['id']}/payments", ['amount' => 0, 'payment_method' => 'cash'])->assertUnprocessable();
    }

    public function test_returns_take_stock_from_the_purchased_lot_and_credit_the_supplier(): void
    {
        [$black] = $this->variants();
        // Older, cheaper stock that FIFO would take first.
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $black, 'qty' => 5, 'unit_cost' => 5000]]])->assertCreated();
        $supplier = $this->supplier();
        $purchase = $this->purchase($supplier['id'], [['variant_id' => $black, 'qty' => 10, 'unit_cost' => 10000]]);
        $itemId = $purchase['items'][0]['id'];

        $after = $this->postJson("/api/v1/purchases/{$purchase['id']}/returns", ['items' => [['purchase_item_id' => $itemId, 'qty' => 3]], 'notes' => 'مكسورة'])
            ->assertCreated()->json('data');

        $this->assertSame([30000, 3], [$after['returned'], $after['items'][0]['returned_qty']]);
        $this->assertSame('PRT-00001', $after['returns'][0]['reference']);
        $this->assertSame(12, $this->stock($black));
        $this->assertSame(100000 - 30000, $this->balance($supplier['id']));
        $this->assertSame([5, 7], $this->inShop(fn () => StockLot::query()->orderBy('received_at')->pluck('qty_remaining')->all()), 'the opening lot is untouched');

        $this->postJson("/api/v1/purchases/{$purchase['id']}/returns", ['items' => [['purchase_item_id' => $itemId, 'qty' => 8]]])
            ->assertUnprocessable()->assertJsonPath('code', 'return_exceeds_purchase')->assertJsonPath('max', 7);
    }

    public function test_the_variant_picker_puts_an_exact_barcode_first_with_its_cost(): void
    {
        [$black] = $this->variants();
        $supplier = $this->supplier();
        $this->purchase($supplier['id'], [['variant_id' => $black, 'qty' => 1, 'unit_cost' => 7500]]);

        $found = $this->getJson('/api/v1/purchases/variants?q=222')->assertOk()->json('data');
        $this->assertSame(['جراب — أحمر', true, null], [$found[0]['display_name'], $found[0]['exact_barcode'], $found[0]['avg_cost']]);

        $black = collect($this->getJson('/api/v1/purchases/variants?q='.urlencode('جراب'))->json('data'))->firstWhere('variant_name', 'أسود');
        $this->assertSame(7500, $black['avg_cost']);
    }

    public function test_statement_is_append_only(): void
    {
        $this->supplier(['opening_balance' => 1000]);

        $this->expectException(LogicException::class);
        $this->inShop(fn () => SupplierTransaction::query()->firstOrFail()->delete());
    }

    public function test_permissions(): void
    {
        $supplier = $this->supplier();
        $roleId = fn (string $key) => $this->inShop(fn () => Role::query()->where('key', $key)->value('id'));
        $staff = function (string $role, string $phone) use ($roleId): User {
            $id = $this->postJson('/api/v1/users', ['name' => $role, 'phone' => $phone, 'password' => 'password', 'role_id' => $roleId($role), 'branch_ids' => [$this->branchId]])
                ->assertCreated()->json('data.id');

            return User::query()->findOrFail($id);
        };
        $cashier = $staff('cashier', '01144445555');
        $storekeeper = $staff('storekeeper', '01166667777');

        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/suppliers')->assertForbidden();
        $this->getJson('/api/v1/purchases')->assertForbidden();
        $this->postJson("/api/v1/suppliers/{$supplier['id']}/payments", ['amount' => 1, 'payment_method' => 'cash'])->assertForbidden();

        Sanctum::actingAs($storekeeper);
        $this->getJson('/api/v1/suppliers')->assertOk()->assertJsonCount(1, 'data');
        $this->postJson('/api/v1/suppliers', ['name' => 'تاني'])->assertCreated();
    }

    public function test_a_shop_cannot_use_another_shops_suppliers_or_items(): void
    {
        [$black] = $this->variants();
        $supplier = $this->supplier();
        $purchase = $this->purchase($supplier['id'], [['variant_id' => $black, 'qty' => 1, 'unit_cost' => 100]]);

        Sanctum::actingAs($other = $this->newShop());
        $otherSupplier = $this->supplier(['name' => 'مورد تاني']);

        $this->getJson("/api/v1/suppliers/{$supplier['id']}")->assertNotFound();
        $this->getJson("/api/v1/purchases/{$purchase['id']}")->assertNotFound();
        $this->getJson('/api/v1/purchases')->assertOk()->assertJsonCount(0, 'data');
        $this->postJson("/api/v1/purchases/{$purchase['id']}/returns", ['items' => [['purchase_item_id' => $purchase['items'][0]['id'], 'qty' => 1]]])->assertNotFound();
        $this->postJson('/api/v1/purchases', ['supplier_id' => $supplier['id'], 'invoice_date' => now()->toDateString(), 'items' => [['variant_id' => $black, 'qty' => 1, 'unit_cost' => 1]]])
            ->assertNotFound()->assertJsonPath('code', 'supplier_not_found');
        $this->postJson('/api/v1/purchases', ['supplier_id' => $otherSupplier['id'], 'invoice_date' => now()->toDateString(), 'items' => [['variant_id' => $black, 'qty' => 1, 'unit_cost' => 1]]])
            ->assertNotFound()->assertJsonPath('code', 'variant_not_found');
        $this->assertSame(0, $this->balance($otherSupplier['id']));
    }
}
