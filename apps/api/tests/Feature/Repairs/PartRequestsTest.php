<?php

namespace Tests\Feature\Repairs;

use App\Modules\Inventory\Contracts\StockLedger;
use App\Support\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/**
 * «الفني يطلب القطع من المخزن بسعر الفني»: the technician asks, the store hands it out at the
 * technician price, and the technician never sees another price.
 */
class PartRequestsTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private string $ticket;

    /** A screen protector: retail 150 ج, technician 90 ج. */
    private string $glass;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->glass = $this->variant('اسكرينة', 'جرابات', ['barcode' => 'GLASS-1', 'price_retail' => 15000, 'price_wholesale' => 11000, 'price_technician' => 9000]);
        $this->postJson('/api/v1/inventory/opening', ['items' => [['variant_id' => $this->glass, 'qty' => 10, 'unit_cost' => 5000]]])->assertCreated();
        $this->ticket = $this->postJson('/api/v1/repairs/tickets', [
            'customer_name' => 'محمود', 'customer_phone' => '01234567890',
            'device_name' => 'Samsung Galaxy A54', 'reported_note' => 'الشاشة مكسورة',
        ])->assertCreated()->json('data.id');
    }

    private function switchOn(): void
    {
        Sanctum::actingAs($this->owner);
        $this->putJson('/api/v1/features/repairs.part_requests', ['enabled' => true])->assertOk();
    }

    private function stock(string $variantId): int
    {
        return $this->inShop(fn () => app(StockLedger::class)->quantity($this->branchId, $variantId));
    }

    public function test_the_switch_is_off_by_default_and_parts_stay_at_retail(): void
    {
        $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/part-requests", ['variant_id' => $this->glass, 'qty' => 1])
            ->assertForbidden()->assertJsonPath('code', 'feature_disabled');
        $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/parts", ['variant_id' => $this->glass, 'qty' => 1])
            ->assertOk()->assertJsonPath('data.parts.0.unit_price', 15000);

        $technician = $this->staff('technician');
        Sanctum::actingAs($technician);
        $this->assertSame(15000, $this->getJson('/api/v1/inventory/price-check?q=GLASS-1')->json('data.0.prices.retail'));
    }

    public function test_the_technician_asks_and_the_store_hands_it_out_at_the_technician_price(): void
    {
        $this->switchOn();
        $technician = $this->staff('technician');
        $storekeeper = $this->staff('storekeeper');

        Sanctum::actingAs($technician);
        // Only the technician price, wherever a price shows.
        $item = $this->getJson('/api/v1/inventory/price-check?q=GLASS-1')->assertOk()->json('data.0');
        $this->assertSame(['retail' => null, 'wholesale' => null, 'technician' => 9000], $item['prices']);
        $this->assertTrue($item['technician_only']);
        $variant = collect($this->getJson('/api/v1/products?q=اسكرينة')->assertOk()->json('data.0.variants'))->firstWhere('id', $this->glass);
        $this->assertSame([null, null, 9000, null], [$variant['price_retail'], $variant['price_wholesale'], $variant['price_technician'], $variant['price_online']]);
        $row = collect($this->getJson('/api/v1/inventory?q=اسكرينة')->assertOk()->json('data'))->firstWhere('id', $this->glass);
        $this->assertSame([null, 9000], [$row['price_retail'], $row['price_technician']]);

        // A part goes on the ticket only through the store.
        $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/parts", ['variant_id' => $this->glass, 'qty' => 1])
            ->assertForbidden()->assertJsonPath('code', 'part_request_required');
        $ticket = $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/part-requests", ['variant_id' => $this->glass, 'qty' => 2, 'note' => 'للشاشة'])
            ->assertOk()->json('data');
        $this->assertSame([['اسكرينة', 2, 'requested', 'technician']], array_map(fn ($r) => [$r['name'], $r['qty'], $r['status'], $r['requested_by_name']], $ticket['part_requests']));
        $this->assertSame([], $ticket['parts']);
        $this->assertSame(10, $this->stock($this->glass), 'nothing leaves stock yet');
        $this->assertSame(1, $this->getJson('/api/v1/repairs/part-requests')->assertOk()->json('meta.waiting'), 'the technician sees their own');
        $request = $ticket['part_requests'][0]['id'];
        $this->postJson("/api/v1/repairs/part-requests/{$request}/hand")->assertForbidden();

        // The store sees the queue (with stock) and the bell, and hands it out.
        Sanctum::actingAs($storekeeper);
        $queue = $this->getJson('/api/v1/repairs/part-requests')->assertOk()->json('data');
        $this->assertSame([['RP-00001', 'Samsung Galaxy A54', 10, 'للشاشة']], array_map(fn ($r) => [$r['ticket']['reference'], $r['ticket']['device_name'], $r['stock'], $r['note']], $queue));
        $bell = $this->getJson('/api/v1/notifications')->assertOk()->json('data.0');
        $this->assertSame(['طلب قطعة للتذكرة RP-00001', '/repairs/parts'], [$bell['title'], $bell['to']]);
        $this->postJson("/api/v1/repairs/part-requests/{$request}/hand")->assertOk()->assertJsonPath('data.status', 'handed');
        $this->postJson("/api/v1/repairs/part-requests/{$request}/hand")->assertUnprocessable()->assertJsonPath('code', 'part_request_closed');
        $this->assertSame(8, $this->stock($this->glass));
        $this->assertSame(1, AuditEntry::query()->where('action', 'repairs.part_handed')->count());

        // On the ticket at the technician price; the technician's margin goes in the labor.
        Sanctum::actingAs($technician);
        $ticket = $this->getJson("/api/v1/repairs/tickets/{$this->ticket}")->json('data');
        $this->assertSame([[2, 9000, 'technician']], array_map(fn ($p) => [$p['qty'], $p['unit_price'], $p['added_by_name']], $ticket['parts']));
        $this->assertSame([18000, 18000], [$ticket['parts_total'], $ticket['total']]);
        $this->assertSame([], $ticket['part_requests']);
        $this->assertContains('part_request', array_column($ticket['events'], 'type'));
        $this->assertSame(0, $this->getJson('/api/v1/repairs/part-requests')->json('meta.waiting'));

        // The owner still adds parts directly — at the technician price too.
        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/parts", ['variant_id' => $this->glass, 'qty' => 1])
            ->assertOk()->assertJsonPath('data.parts.1.unit_price', 9000);
        // A part without a technician price goes at retail.
        $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/parts", ['variant_id' => $this->v[1], 'qty' => 1])
            ->assertOk()->assertJsonPath('data.parts.2.unit_price', 45000);
        $this->assertSame(15000, $this->getJson('/api/v1/inventory/price-check?q=GLASS-1')->json('data.0.prices.retail'), 'the owner sees every price');
    }

    public function test_refused_and_taken_back_requests(): void
    {
        $this->switchOn();
        $technician = $this->staff('technician');
        $cashier = $this->staff('cashier');

        Sanctum::actingAs($technician);
        $one = $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/part-requests", ['variant_id' => $this->glass, 'qty' => 1])->json('data.part_requests.0.id');
        $two = $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/part-requests", ['variant_id' => $this->v[1], 'qty' => 1])->json('data.part_requests.1.id');
        $this->deleteJson("/api/v1/repairs/part-requests/{$two}")->assertOk()->assertJsonPath('data.status', 'cancelled');

        Sanctum::actingAs($cashier);
        $this->postJson("/api/v1/repairs/part-requests/{$one}/reject", [])->assertUnprocessable()->assertJsonValidationErrors('reason');
        $this->postJson("/api/v1/repairs/part-requests/{$one}/reject", ['reason' => 'خلصانة، هتيجي بكرة'])->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame([], $this->getJson('/api/v1/repairs/part-requests')->json('data'));
        $this->assertEqualsCanonicalizing(['rejected', 'cancelled'], array_column($this->getJson('/api/v1/repairs/part-requests?status=done')->json('data'), 'status'));

        Sanctum::actingAs($technician);
        $ticket = $this->getJson("/api/v1/repairs/tickets/{$this->ticket}")->json('data');
        $this->assertSame([['rejected', 'خلصانة، هتيجي بكرة'], ['cancelled', null]], array_map(fn ($r) => [$r['status'], $r['reason']], $ticket['part_requests']));
        $this->assertSame(10, $this->stock($this->glass));

        // Someone else's request can't be taken back by a technician; a closed ticket takes no requests.
        Sanctum::actingAs($this->owner);
        $other = $this->staff('technician');
        Sanctum::actingAs($technician);
        $three = $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/part-requests", ['variant_id' => $this->glass, 'qty' => 1])->json('data.part_requests.2.id');
        Sanctum::actingAs($other);
        $this->deleteJson("/api/v1/repairs/part-requests/{$three}")->assertForbidden();
        $this->assertSame([], $this->getJson('/api/v1/repairs/part-requests')->json('data'), 'a technician sees only their own');

        Sanctum::actingAs($this->owner);
        $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/status", ['status' => 'rejected'])->assertOk();
        $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/deliver", [])->assertOk();
        $this->assertSame([], $this->getJson('/api/v1/repairs/part-requests')->json('data'), 'delivering closes what was still asked for');
        $this->postJson("/api/v1/repairs/tickets/{$this->ticket}/part-requests", ['variant_id' => $this->glass, 'qty' => 1])
            ->assertUnprocessable()->assertJsonPath('code', 'ticket_closed');
    }
}
