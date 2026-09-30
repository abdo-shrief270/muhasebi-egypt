<?php

namespace Tests\Feature\Modules;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\User;
use App\Support\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** The owner's small switches inside the modules (the «المميزات» page). */
class FeatureSwitchesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->openShift(10000);
    }

    private function switch(string $key, bool $on): void
    {
        $this->putJson("/api/v1/features/{$key}", ['enabled' => $on])->assertOk();
    }

    public function test_the_owner_sees_the_switches_of_the_modules_in_use_with_their_defaults(): void
    {
        $groups = collect($this->getJson('/api/v1/features')->assertOk()->json('data'))->keyBy('module');
        $sales = collect($groups['sales']['features'])->keyBy('key');
        $this->assertTrue($sales['sales.discounts']['enabled']);
        $this->assertFalse($sales['sales.block_out_of_stock']['enabled']);
        $this->assertArrayHasKey('repairs', $groups->all(), 'repairs is on trial for this shop');

        $this->switch('sales.block_out_of_stock', true);
        $this->assertTrue($this->getJson('/api/v1/auth/me')->json('data.features')['sales.block_out_of_stock']);
        $this->assertTrue(AuditEntry::query()->where('action', 'features.enabled')->exists());

        $this->putJson('/api/v1/features/nope.nope', ['enabled' => true])->assertNotFound();
        Sanctum::actingAs($this->staff('manager'));
        $this->getJson('/api/v1/features')->assertForbidden();
        $this->putJson('/api/v1/features/sales.discounts', ['enabled' => false])->assertForbidden();
    }

    public function test_excel_import_and_report_export_can_be_closed(): void
    {
        $this->switch('catalog.excel_import', false);
        $this->getJson('/api/v1/products/import/template')->assertForbidden()->assertJsonPath('code', 'feature_disabled');

        $this->switch('reports.excel_export', false);
        $this->get('/api/v1/reports/sales/export', ['Accept' => 'application/json'])->assertForbidden()->assertJsonPath('code', 'feature_disabled');
        $this->getJson('/api/v1/reports/sales')->assertOk();
    }

    public function test_discounts_and_selling_beyond_stock(): void
    {
        $this->switch('sales.discounts', false);
        $this->sell([['method' => 'cash', 'amount' => 40000]], ['discount' => 5000])->assertForbidden()->assertJsonPath('code', 'discount_not_allowed');
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();

        // 20 chargers in stock, one sold.
        $this->switch('sales.block_out_of_stock', true);
        $this->sell([['method' => 'cash', 'amount' => 45000 * 20]], ['items' => [['variant_id' => $this->v[1], 'qty' => 20]]])
            ->assertStatus(422)->assertJsonPath('code', 'out_of_stock')->assertJsonPath('available', 19);
        $this->sell([['method' => 'cash', 'amount' => 45000 * 19]], ['items' => [['variant_id' => $this->v[1], 'qty' => 19]]])->assertCreated();
    }

    public function test_receipt_links_and_repair_tracking_can_be_closed(): void
    {
        $sale = $this->sell([['method' => 'cash', 'amount' => 45000]])->json('data');
        $this->getJson("/api/v1/public/receipts/{$sale['public_token']}")->assertOk();
        $this->switch('sales.receipt_link', false);
        $this->getJson("/api/v1/public/receipts/{$sale['public_token']}")->assertNotFound();

        $ticket = $this->postJson('/api/v1/repairs/tickets', ['customer_name' => 'نادر', 'customer_phone' => '01234567890', 'device_name' => 'iPhone 11', 'reported_note' => 'شاشة'])->assertCreated()->json('data');
        $this->getJson("/api/v1/public/repairs/{$ticket['public_token']}")->assertOk();
        $this->switch('repairs.public_tracking', false);
        $this->getJson("/api/v1/public/repairs/{$ticket['public_token']}")->assertNotFound();
    }

    public function test_a_seller_keeps_its_prices_until_the_order_is_ready(): void
    {
        $seller = $this->owner;
        $this->switch('shop_orders.prices_after_review', true);
        $buyer = $this->registerShop(ShopType::Accessories);

        Sanctum::actingAs($buyer);
        $id = $this->postJson('/api/v1/shop-connections', ['code' => $seller->tenant()->firstOrFail()->code])->assertCreated()->json('data.id');
        Sanctum::actingAs($seller);
        $this->postJson("/api/v1/shop-connections/{$id}/accept")->assertOk();

        Sanctum::actingAs($buyer);
        $order = $this->postJson('/api/v1/shop-orders', ['seller_tenant_id' => $seller->tenant_id, 'type' => 'goods', 'items' => [['description' => 'شاحن', 'quantity' => 2]]])->assertCreated()->json('data');
        $move = function (User $as, string $to, array $extra = []) use ($order) {
            Sanctum::actingAs($as);

            return $this->postJson("/api/v1/shop-orders/{$order['id']}/transition", ['status' => $to, ...$extra])->assertOk()->json('data');
        };

        $itemId = $order['items'][0]['id'] ?? $this->getJson("/api/v1/shop-orders/{$order['id']}")->json('data.items.0.id');
        $seen = $move($seller, 'accepted', ['prices' => [$itemId => 40000]]);
        $this->assertSame(80000, $seen['total'], 'the seller sees its own prices');

        Sanctum::actingAs($buyer);
        $asBuyer = $this->getJson("/api/v1/shop-orders/{$order['id']}")->json('data');
        $this->assertSame([null, null, true], [$asBuyer['total'], $asBuyer['items'][0]['unit_price'], $asBuyer['prices_hidden']]);

        $move($seller, 'preparing');
        $move($seller, 'ready');
        Sanctum::actingAs($buyer);
        $asBuyer = $this->getJson("/api/v1/shop-orders/{$order['id']}")->json('data');
        $this->assertSame([80000, 40000, false], [$asBuyer['total'], $asBuyer['items'][0]['unit_price'], $asBuyer['prices_hidden']]);
    }
}
