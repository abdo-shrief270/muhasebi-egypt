<?php

namespace Tests\Feature\Reports;

use App\Modules\Identity\PermissionResolver;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** The owner app's «النهارده» and «اللي بيحصل». */
class OwnerScreensTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(CarbonImmutable::now('Africa/Cairo')->setTime(15, 0));
        $this->openShopWithStock();
        $this->postJson('/api/v1/modules/owner_app/trial')->assertOk();
        app(PermissionResolver::class)->forget();
    }

    public function test_today_so_far_against_yesterday(): void
    {
        $this->travelTo(CarbonImmutable::now('Africa/Cairo')->subDay()->setTime(11, 0));
        $shift = $this->openShift(10000)->json('data');
        $this->travel(1)->minutes();
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();
        $this->travel(1)->minutes();
        $this->postJson("/api/v1/cash/shifts/{$shift['id']}/close", ['counted' => ['cash' => 65000]])->assertOk();

        $this->travelTo(CarbonImmutable::now('Africa/Cairo')->addDay()->setTime(15, 0));
        $this->openShift(20000);
        $this->travel(1)->minutes();
        $sale = $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated()->json('data');
        $this->travel(1)->minutes();
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();
        $this->travel(1)->minutes();
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => true]]])->assertCreated();
        $this->travel(1)->minutes();
        $this->postJson('/api/v1/cash/movements', ['type' => 'expense', 'amount' => 5000, 'category' => 'other', 'note' => 'شاي'])->assertCreated();

        $today = $this->getJson('/api/v1/owner/today')->assertOk()->json('data');
        $this->assertSame([45000, 2, 45000 - 30000, 45000], [$today['sales']['net'], $today['sales']['invoices'], $today['sales']['profit'], $today['sales']['same_time_yesterday']]);
        $this->assertSame([1, 45000, 5000], [$today['returns']['count'], $today['returns']['amount'], $today['expenses']]);
        $this->assertCount(1, $today['open_shifts']);
        $this->assertSame(20000 + 90000 - 45000 - 5000, $today['open_shifts'][0]['expected_cash']);
        $this->assertSame('شاحن 20W', $today['top_items'][0]['name']);
        $this->assertSame(45000, $today['by_hour'][15]['today'], 'net of the return');
        $this->assertNull($today['by_hour'][16]['today'], 'hours still to come');
        $this->assertSame(45000, $today['by_hour'][11]['yesterday']);

        $feed = $this->getJson('/api/v1/owner/feed')->assertOk()->json('data');
        $this->assertSame(['expense', 'return', 'sale', 'sale', 'shift_opened', 'shift_closed', 'sale', 'shift_opened'], array_column($feed, 'kind'));
        $this->assertSame([-5000, -45000], [$feed[0]['amount'], $feed[1]['amount']]);
        $this->assertStringContainsString('بزيادة 100 ج', $feed[5]['title']);
        $this->assertSame(['shift_opened', 'shift_closed', 'shift_opened'], array_column($this->getJson('/api/v1/owner/feed?kinds[]=shift')->json('data'), 'kind'));
        $older = $this->getJson('/api/v1/owner/feed?before='.urlencode($feed[3]['at']))->json('data');
        $this->assertSame('shift_opened', $older[0]['kind']);
    }

    public function test_only_owners_and_managers_see_it(): void
    {
        $cashier = $this->staff('cashier');
        $manager = $this->staff('manager');
        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/owner/today')->assertForbidden();
        Sanctum::actingAs($manager);
        $this->getJson('/api/v1/owner/today')->assertOk()->assertJsonPath('data.sales.profit', 0);
        $this->getJson('/api/v1/owner/today?branch=01a00000-0000-7000-8000-000000000000')->assertForbidden();
    }
}
