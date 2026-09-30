<?php

namespace Tests\Feature\Sales;

use App\Modules\Catalog\Models\Product;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Sales\Models\Sale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/**
 * The POS keeps selling when the internet drops: it caches the catalog (`/pos/catalog`) and queues
 * sales, which reach the API later with `offline: true` and the time they were made (`sold_at`).
 */
class OfflineSalesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openShopWithStock();
        $this->openShift();
    }

    private function queued(array $overrides = [])
    {
        return $this->sell([['method' => 'cash', 'amount' => 45000]], ['id' => (string) Str::uuid7(), ...$overrides]);
    }

    private function stock(string $variantId): int
    {
        return $this->inShop(fn () => app(StockLedger::class)->quantity($this->branchId, $variantId));
    }

    public function test_an_offline_sale_keeps_the_time_it_was_made(): void
    {
        $soldAt = now()->subHours(3)->startOfSecond();

        $sale = $this->queued(['offline' => true, 'sold_at' => $soldAt->toIso8601String()])->assertCreated()->json('data');

        $this->assertTrue(Carbon::parse($sale['completed_at'])->equalTo($soldAt));
        $this->assertTrue($this->inShop(fn () => Sale::query()->findOrFail($sale['id'])->completed_at->equalTo($soldAt)));
        $this->assertSame(19, $this->stock($this->v[1]));
    }

    public function test_sold_at_needs_the_offline_flag(): void
    {
        $this->queued(['sold_at' => now()->subHour()->toIso8601String()])->assertStatus(422)->assertJsonValidationErrors('sold_at');
        $this->queued(['offline' => false, 'sold_at' => now()->subHour()->toIso8601String()])->assertStatus(422)->assertJsonValidationErrors('sold_at');

        // offline without a time: sold now.
        $sale = $this->queued(['offline' => true])->assertCreated()->json('data');
        $this->assertTrue(Carbon::parse($sale['completed_at'])->diffInSeconds(now(), true) < 5);
    }

    public function test_sold_at_bounds(): void
    {
        $this->queued(['offline' => true, 'sold_at' => now()->addMinutes(30)->toIso8601String()])->assertStatus(422)->assertJsonValidationErrors('sold_at');
        $this->queued(['offline' => true, 'sold_at' => now()->subDays(8)->toIso8601String()])->assertStatus(422)->assertJsonValidationErrors('sold_at');
        $this->queued(['offline' => true, 'sold_at' => 'yesterday-ish'])->assertStatus(422)->assertJsonValidationErrors('sold_at');

        // A device clock a little ahead and a sale from six days ago are fine.
        $this->queued(['offline' => true, 'sold_at' => now()->addMinutes(2)->toIso8601String()])->assertCreated();
        $this->queued(['offline' => true, 'sold_at' => now()->subDays(6)->toIso8601String()])->assertCreated();
        $this->assertSame(0, $this->stock($this->v[0]) - 50, 'the case was not touched');
        $this->assertSame(18, $this->stock($this->v[1]));
    }

    public function test_a_queued_sale_sent_again_is_saved_once(): void
    {
        $body = ['id' => (string) Str::uuid7(), 'offline' => true, 'sold_at' => now()->subMinutes(10)->toIso8601String()];

        $first = $this->queued($body)->assertCreated()->json('data');
        $second = $this->queued($body)->assertCreated()->json('data');

        $this->assertSame([$first['id'], $first['number']], [$second['id'], $second['number']]);
        $this->assertSame(19, $this->stock($this->v[1]));
        $this->assertSame(1, $this->inShop(fn () => Sale::query()->count()));
    }

    public function test_a_queued_sale_after_the_shift_was_closed_is_refused(): void
    {
        $shift = $this->getJson('/api/v1/cash/current')->json('data');
        $this->postJson("/api/v1/cash/shifts/{$shift['id']}/close", ['counted' => ['cash' => 0]])->assertOk();

        $this->queued(['offline' => true, 'sold_at' => now()->subMinutes(10)->toIso8601String()])
            ->assertStatus(409)->assertJsonPath('code', 'shift_not_open');
        $this->assertSame(20, $this->stock($this->v[1]));
    }

    public function test_the_pos_catalog_lists_every_sellable_item_with_stock_here(): void
    {
        $phone = $this->variant('Samsung A15', 'موبايلات جديدة', ['barcode' => 'A15', 'price_retail' => 700000, 'price_wholesale' => 650000]);
        $product = $this->getJson('/api/v1/products?q=A15')->json('data.0');
        $this->patchJson("/api/v1/products/{$product['id']}", ['track_serial' => true])->assertOk();
        $supplierId = $this->postJson('/api/v1/suppliers', ['name' => 'موزع سامسونج'])->assertCreated()->json('data.id');
        $this->postJson('/api/v1/purchases', [
            'supplier_id' => $supplierId,
            'invoice_date' => now()->toDateString(),
            'items' => [['variant_id' => $phone, 'qty' => 2, 'unit_cost' => 600000, 'serials' => ['351234567890121', '351234567890139']]],
        ])->assertCreated();
        $stopped = $this->variant('جراب قديم', 'جرابات', ['price_retail' => 5000, 'is_active' => false]);

        $res = $this->getJson('/api/v1/pos/catalog')->assertOk();
        $items = collect($res->json('data'))->keyBy('id');

        $this->assertSame(1, $res->json('meta.last_page'));
        $this->assertSame(3, $res->json('meta.total'));
        $this->assertFalse($items->has($stopped), 'stopped items are not sold');
        $this->assertSame([50, 'CASE-1', null], [$items[$this->v[0]]['qty'], $items[$this->v[0]]['barcode'], $items[$this->v[0]]['serials']]);
        $this->assertSame([2, true, 700000, 650000, ['351234567890121', '351234567890139']], [
            $items[$phone]['qty'], $items[$phone]['track_serial'], $items[$phone]['price_retail'], $items[$phone]['price_wholesale'], $items[$phone]['serials'],
        ]);
        $this->assertSame('موبايلات جديدة', $items[$phone]['category']['name']);
        $this->assertNotNull($res->json('meta.generated_at'));

        // A sold unit leaves the list.
        $this->postJson('/api/v1/sales', ['items' => [['variant_id' => $phone, 'qty' => 1, 'serials' => ['351234567890121']]], 'payments' => [['method' => 'cash', 'amount' => 700000]]])->assertCreated();
        $this->assertSame(['351234567890139'], collect($this->getJson('/api/v1/pos/catalog')->json('data'))->firstWhere('id', $phone)['serials']);
    }

    public function test_the_pos_catalog_is_paged_by_500(): void
    {
        $productId = $this->getJson('/api/v1/products?q=CASE-1')->json('data.0.id');
        $variants = array_map(fn (int $i) => ['name' => "لون {$i}", 'price_retail' => 10000 + $i], range(1, 520));
        $this->inShop(function () use ($productId, $variants): void {
            $product = Product::query()->findOrFail($productId);
            foreach ($variants as $i => $v) {
                $product->variants()->create([...$v, 'tenant_id' => $product->tenant_id, 'sort' => $i + 1]);
            }
        });

        $first = $this->getJson('/api/v1/pos/catalog')->assertOk();
        $second = $this->getJson('/api/v1/pos/catalog?page=2')->assertOk();

        $this->assertSame([522, 2, 500], [$first->json('meta.total'), $first->json('meta.last_page'), count($first->json('data'))]);
        $this->assertCount(22, $second->json('data'));
        $this->assertSame([], array_intersect(array_column($first->json('data'), 'id'), array_column($second->json('data'), 'id')));
    }

    public function test_the_pos_catalog_needs_sales_sell_and_shows_the_branch_stock(): void
    {
        $this->postJson('/api/v1/modules/multi_branch/trial')->assertOk();
        $second = $this->postJson('/api/v1/branches', ['name' => 'فرع 2'])->assertCreated()->json('data.id');
        $there = collect($this->withHeader('X-Branch-Id', $second)->getJson('/api/v1/pos/catalog')->assertOk()->json('data'))->keyBy('id');
        $this->assertSame(0, $there[$this->v[0]]['qty'], 'stock is per branch');

        $technician = $this->staff('technician');
        $cashier = $this->staff('cashier');

        Sanctum::actingAs($technician);
        $this->withHeader('X-Branch-Id', $this->branchId)->getJson('/api/v1/pos/catalog')->assertForbidden();

        Sanctum::actingAs($cashier);
        $this->withHeader('X-Branch-Id', $this->branchId)->getJson('/api/v1/pos/catalog')->assertOk()->assertJsonPath('meta.total', 2);
    }
}
