<?php

namespace Tests\Feature\Inventory;

use App\Modules\Identity\Models\Role;
use App\Modules\Identity\PermissionResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class PriceCheckTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
    }

    public function test_the_owner_sees_prices_cost_and_stock_with_the_scanned_item_first(): void
    {
        $items = $this->getJson('/api/v1/inventory/price-check?q=CHG-1')->assertOk()->json('data');

        $this->assertSame('شاحن 20W', $items[0]['display_name']);
        $this->assertTrue($items[0]['exact_barcode']);
        $this->assertSame(45000, $items[0]['prices']['retail']);
        $this->assertSame(30000, $items[0]['cost']);
        $this->assertSame([['qty' => 20, 'current' => true]], array_map(fn ($s) => ['qty' => $s['qty'], 'current' => $s['current']], $items[0]['stock']));

        $this->getJson('/api/v1/inventory/price-check?q=ش')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_a_cashier_sees_the_retail_price_and_stock_only(): void
    {
        Sanctum::actingAs($this->staff('cashier'));

        $item = $this->getJson('/api/v1/inventory/price-check?q='.urlencode('شاحن'))->assertOk()->json('data.0');
        $this->assertSame([45000, null, null], array_values($item['prices']));
        $this->assertNull($item['cost']);
        $this->assertSame(20, $item['stock'][0]['qty']);
    }

    public function test_it_needs_products_view(): void
    {
        Sanctum::actingAs($this->staff('technician'));
        $this->getJson('/api/v1/inventory/price-check?q=CHG-1')->assertOk();

        $role = $this->inShop(fn () => Role::query()->where('key', 'technician')->firstOrFail());
        $role->update(['permissions' => ['repairs.view']]);
        app(PermissionResolver::class)->forget();
        $this->getJson('/api/v1/inventory/price-check?q=CHG-1')->assertForbidden();
    }
}
