<?php

namespace Tests\Feature\Sales;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** The cashier's hint: what this customer paid for an item before. */
class LastPricesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    public function test_the_last_prices_a_customer_paid_newest_first(): void
    {
        $this->openShopWithStock();
        $this->openShift();
        $customer = $this->postJson('/api/v1/customers', ['name' => 'محمد علي', 'phone' => '01012345678'])->assertCreated()->json('data.id');
        $other = $this->postJson('/api/v1/customers', ['name' => 'سامي', 'phone' => '01099998888'])->assertCreated()->json('data.id');

        // Charger 450, then 2 cases with 10 ج off the line (90 each), then a charger for someone else.
        $this->sell([['method' => 'cash', 'amount' => 45000]], ['customer_id' => $customer])->assertCreated();
        $this->travel(1)->days();
        $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $this->v[0], 'qty' => 2, 'discount' => 2000]],
            'payments' => [['method' => 'cash', 'amount' => 18000]],
            'customer_id' => $customer,
        ])->assertCreated();
        $this->sell([['method' => 'cash', 'amount' => 45000]], ['customer_id' => $other])->assertCreated();

        $data = $this->getJson('/api/v1/pos/last-prices?'.http_build_query(['customer_id' => $customer, 'variant_ids' => $this->v]))->assertOk()->json('data');
        $this->assertSame([45000], array_column($data[$this->v[1]], 'price'));
        $this->assertSame([9000, 10000, 2], [$data[$this->v[0]][0]['price'], $data[$this->v[0]][0]['list_price'], $data[$this->v[0]][0]['qty']]);
        $this->assertStringStartsWith('INV-', $data[$this->v[0]][0]['reference']);

        // Nothing bought: an empty object, not a list.
        $this->assertSame('{"data":{}}', $this->getJson('/api/v1/pos/last-prices?'.http_build_query(['customer_id' => $other, 'variant_ids' => [$this->v[0]]]))->getContent());
    }
}
