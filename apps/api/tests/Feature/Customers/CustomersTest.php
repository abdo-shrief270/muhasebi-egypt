<?php

namespace Tests\Feature\Customers;

use App\Modules\Customers\Models\CustomerTransaction;
use App\Modules\Identity\Enums\ShopType;
use App\Support\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class CustomersTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->openShift(10000);
    }

    private function customer(array $data = []): array
    {
        return $this->postJson('/api/v1/customers', ['name' => 'أحمد علي', 'phone' => '01012345678', ...$data])->assertCreated()->json('data');
    }

    public function test_customers_are_found_by_name_or_phone(): void
    {
        $ahmed = $this->customer();
        $this->customer(['name' => 'منة الله', 'phone' => null]);
        $this->customer(['name' => 'منى', 'phone' => '+201198765432']);

        $this->assertSame('+201012345678', $ahmed['phone'], 'stored as E.164');
        $this->postJson('/api/v1/customers', ['name' => 'تاني', 'phone' => '010 1234 5678'])->assertUnprocessable()->assertJsonValidationErrors('phone');
        $this->postJson('/api/v1/customers', ['name' => 'غلط', 'phone' => '123'])->assertUnprocessable()->assertJsonValidationErrors('phone');

        $names = fn (string $q) => array_column($this->getJson('/api/v1/customers?q='.urlencode($q))->assertOk()->json('data'), 'name');
        $this->assertSame(['أحمد علي'], $names('احمد'), 'أ/ا spelled either way');
        $this->assertSame(['أحمد علي'], $names('0101234'));
        $this->assertSame(['منى'], $names('01198'));
        $this->assertEqualsCanonicalizing(['منة الله', 'منى'], $names('من'));
    }

    public function test_selling_on_credit_charges_the_account_within_its_limit(): void
    {
        $customer = $this->customer(['credit_limit' => 60000]);

        $this->sell([['method' => 'credit', 'amount' => 45000]])->assertUnprocessable()->assertJsonPath('code', 'credit_needs_customer');

        // 450: 100 cash now, 350 on the account.
        $sale = $this->sell([['method' => 'cash', 'amount' => 10000], ['method' => 'credit', 'amount' => 35000]], ['customer_id' => $customer['id']])
            ->assertCreated()->json('data');
        $this->assertSame([$customer['id'], 'أحمد علي', '+201012345678', 35000], [$sale['customer_id'], $sale['customer_name'], $sale['customer_phone'], $sale['credit']]);
        $this->assertSame(35000, $this->getJson("/api/v1/customers/{$customer['id']}")->json('data.balance'));

        // 350 + 450 > 600
        $this->sell([['method' => 'credit', 'amount' => 45000]], ['customer_id' => $customer['id']])
            ->assertUnprocessable()->assertJsonPath('code', 'credit_limit_exceeded')->assertJsonPath('available', 25000);
        $this->sell([['method' => 'credit', 'amount' => 50000]], ['customer_id' => $customer['id']])
            ->assertUnprocessable()->assertJsonPath('code', 'overpaid_non_cash');

        // Only the cash reached the drawer.
        $this->assertSame(10000 + 10000, $this->getJson('/api/v1/cash/current')->json('data.expected.cash'));

        $statement = $this->getJson("/api/v1/customers/{$customer['id']}/statement")->assertOk()->json('data');
        $this->assertSame([['sale', 35000, 35000, 'INV-000001']], array_map(fn ($t) => [$t['type'], $t['amount'], $t['balance_after'], $t['reference']], $statement));
        $this->assertSame([$sale['id']], array_column($this->getJson("/api/v1/sales?customer_id={$customer['id']}")->json('data'), 'id'));
    }

    public function test_a_sale_all_on_credit_still_needs_a_shift(): void
    {
        $customer = $this->customer();
        $cashier = $this->staff('manager');
        Sanctum::actingAs($cashier);

        $this->sell([['method' => 'credit', 'amount' => 45000]], ['customer_id' => $customer['id']])->assertStatus(409)->assertJsonPath('code', 'shift_not_open');
        $this->openShift();
        $this->sell([['method' => 'credit', 'amount' => 45000]], ['customer_id' => $customer['id']])->assertCreated();
    }

    public function test_collecting_payments_and_returns_on_the_account(): void
    {
        $customer = $this->customer(['opening_balance' => 20000]);
        $sale = $this->sell([['method' => 'credit', 'amount' => 45000]], ['customer_id' => $customer['id']])->assertCreated()->json('data');

        $this->postJson("/api/v1/customers/{$customer['id']}/payments", ['amount' => 30000, 'payment_method' => 'cash', 'note' => 'دفعة'])->assertCreated();
        $this->postJson("/api/v1/customers/{$customer['id']}/payments", ['amount' => 5000, 'payment_method' => 'wallet'])->assertCreated();
        $this->assertSame(20000 + 45000 - 35000, $this->getJson("/api/v1/customers/{$customer['id']}")->json('data.balance'));

        // The charger comes back and comes off the account instead of cash.
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'credit', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => true]]])
            ->assertCreated();
        $this->assertSame(-15000, $this->getJson("/api/v1/customers/{$customer['id']}")->json('data.balance'), 'store credit');

        $current = $this->getJson('/api/v1/cash/current')->json('data');
        $this->assertSame([10000 + 30000, 5000], [$current['expected']['cash'], $current['expected']['wallet']]);

        $types = array_column($this->getJson("/api/v1/customers/{$customer['id']}/statement")->json('data'), 'type');
        $this->assertSame(['sale_return', 'payment', 'payment', 'sale', 'opening'], $types);
        $this->assertSame(2, AuditEntry::query()->where('action', 'customers.paid')->count());

        $cashSale = $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated()->json('data');
        $this->postJson("/api/v1/sales/{$cashSale['id']}/returns", ['refund_method' => 'credit', 'items' => [['sale_item_id' => $cashSale['items'][0]['id'], 'qty' => 1, 'restock' => true]]])
            ->assertUnprocessable()->assertJsonPath('code', 'credit_needs_customer');

        $transaction = $this->inShop(fn () => CustomerTransaction::query()->firstOrFail());
        $this->expectException(LogicException::class);
        $transaction->delete();
    }

    public function test_credit_needs_its_permission(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($this->staff('cashier'));
        $this->openShift();

        $this->getJson('/api/v1/customers')->assertOk();
        $this->postJson('/api/v1/customers', ['name' => 'عميل جديد'])->assertCreated();
        $this->postJson('/api/v1/customers', ['name' => 'بحد', 'credit_limit' => 1000])->assertForbidden();
        $this->sell([['method' => 'cash', 'amount' => 45000]], ['customer_id' => $customer['id']])->assertCreated();
        $this->sell([['method' => 'credit', 'amount' => 45000]], ['customer_id' => $customer['id']])->assertForbidden()->assertJsonPath('code', 'credit_not_allowed');
        $this->postJson("/api/v1/customers/{$customer['id']}/payments", ['amount' => 100, 'payment_method' => 'cash'])->assertForbidden();
    }

    public function test_another_shop_cannot_see_my_customers(): void
    {
        $customer = $this->customer();
        Sanctum::actingAs($this->registerShop(ShopType::Accessories));

        $this->getJson('/api/v1/customers')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/customers/{$customer['id']}")->assertNotFound();
        $this->postJson('/api/v1/customers', ['name' => 'نفس الرقم', 'phone' => '01012345678'])->assertCreated();
    }
}
