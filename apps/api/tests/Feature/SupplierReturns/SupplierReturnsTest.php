<?php

namespace Tests\Feature\SupplierReturns;

use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Models\SerialNumber;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Modules\SupplierReturns\Models\BinItem;
use App\Support\Audit\AuditEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class SupplierReturnsTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** A screen bought from suppliers only (no opening stock): cost 200 ج. */
    private string $screen;

    /** A phone that tracks IMEIs. */
    private string $phone;

    private string $supplierA;

    private string $supplierB;

    protected function setUp(): void
    {
        parent::setUp();

        $this->openShopWithStock();
        $this->openShift(100000);
        $this->screen = $this->variant('شاشة A54', 'شواحن', ['barcode' => 'SCR-1', 'price_retail' => 40000]);
        $this->phone = $this->variant('Samsung A15', 'موبايلات جديدة', ['price_retail' => 700000]);
        $product = $this->getJson('/api/v1/products?q=A15')->json('data.0');
        $this->patchJson("/api/v1/products/{$product['id']}", ['track_serial' => true])->assertOk();
        $this->supplierA = $this->postJson('/api/v1/suppliers', ['name' => 'مورد الشاشات', 'phone' => '01011111111'])->assertCreated()->json('data.id');
        $this->supplierB = $this->postJson('/api/v1/suppliers', ['name' => 'موزع سامسونج'])->assertCreated()->json('data.id');
    }

    private function buy(string $supplierId, string $variantId, int $qty, int $unitCost, ?array $serials = null): array
    {
        return $this->postJson('/api/v1/purchases', [
            'supplier_id' => $supplierId,
            'invoice_date' => now()->toDateString(),
            'items' => [['variant_id' => $variantId, 'qty' => $qty, 'unit_cost' => $unitCost, 'serials' => $serials]],
        ])->assertCreated()->json('data');
    }

    private function toBin(array $body)
    {
        return $this->postJson('/api/v1/supplier-returns/bin', ['reason' => 'defect', ...$body]);
    }

    private function bin(): array
    {
        return $this->getJson('/api/v1/supplier-returns/bin')->assertOk()->json('data');
    }

    private function stock(string $variantId): int
    {
        return $this->inShop(fn () => app(StockLedger::class)->quantity($this->branchId, $variantId));
    }

    private function balance(string $supplierId): int
    {
        return $this->getJson("/api/v1/suppliers/{$supplierId}")->json('data.balance');
    }

    private function serialStatus(string $serial): string
    {
        return $this->inShop(fn () => SerialNumber::query()->where('serial', $serial)->value('status'));
    }

    private function note(array $itemIds): array
    {
        $notes = $this->postJson('/api/v1/supplier-returns/notes', ['item_ids' => $itemIds])->assertCreated()->json('data');
        $this->assertCount(1, $notes);

        return $notes[0];
    }

    public function test_units_taken_out_of_stock_go_to_the_bin_with_the_source_of_their_lot(): void
    {
        $purchase = $this->buy($this->supplierA, $this->screen, 5, 20000);

        $rows = $this->toBin(['variant_id' => $this->screen, 'qty' => 2, 'reason' => 'not_working'])->assertCreated()->json('data');

        $this->assertSame(3, $this->stock($this->screen), 'out of sellable stock');
        $this->assertCount(1, $rows);
        $this->assertSame(['supplier', $this->supplierA, 'lot', 'PUR-00001', 20000, 'مش شغال'], [
            $rows[0]['source']['type'], $rows[0]['source']['id'], $rows[0]['detected_by'], $rows[0]['source_doc'], $rows[0]['unit_cost'], $rows[0]['reason_label'],
        ]);
        $this->assertSame($purchase['items'][0]['lot_id'] ?? $this->inShop(fn () => StockLot::query()->where('source_id', $purchase['id'])->value('id')), $this->inShop(fn () => BinItem::query()->value('lot_id')));
        $movement = $this->inShop(fn () => StockMovement::query()->where('variant_id', $this->screen)->orderByDesc('seq')->first());
        $this->assertSame(['returns_bin', -2], [$movement->type->value, $movement->qty]);

        // «Other» needs a note.
        $this->toBin(['variant_id' => $this->screen, 'qty' => 1, 'reason' => 'other'])->assertUnprocessable()->assertJsonPath('code', 'note_required');
    }

    public function test_the_sorting_screen_groups_the_bin_by_source_and_unknown_sources_are_picked_from_a_short_list(): void
    {
        $this->buy($this->supplierA, $this->screen, 10, 20000);
        $this->toBin(['variant_id' => $this->screen, 'qty' => 3])->assertCreated();
        $this->toBin(['variant_id' => $this->screen, 'qty' => 4, 'reason' => 'shipping_damage'])->assertCreated();
        // The case came from opening stock: no known source.
        $unknown = $this->toBin(['variant_id' => $this->v[0], 'qty' => 2])->assertCreated()->json('data.0');
        $this->assertNull($unknown['source']);

        $bin = $this->bin();
        $this->assertSame(9, $bin['units']);
        $this->assertSame(7 * 20000 + 2 * 4000, $bin['value']);
        $this->assertSame([['مورد الشاشات', 7, 140000], [null, 2, 8000]], array_map(fn ($g) => [$g['source']['name'] ?? null, $g['units'], $g['value']], $bin['groups']));

        $this->postJson('/api/v1/supplier-returns/notes', ['item_ids' => [$unknown['id']]])->assertUnprocessable()->assertJsonPath('code', 'source_required');

        // The case was bought from supplier B once: it's suggested first.
        $this->buy($this->supplierB, $this->v[0], 10, 3500);
        $sources = $this->getJson("/api/v1/supplier-returns/sources?variant_id={$this->v[0]}")->assertOk()->json('data');
        $this->assertSame([[$this->supplierB, 'PUR-00002', 3500]], array_map(fn ($s) => [$s['id'], $s['doc'], $s['unit_cost']], $sources['suggested']));
        $this->assertCount(2, $sources['suppliers']);

        $picked = $this->patchJson("/api/v1/supplier-returns/bin/{$unknown['id']}", ['source' => ['type' => 'supplier', 'id' => $this->supplierB]])->assertOk()->json('data');
        $this->assertSame(['موزع سامسونج', 'manual'], [$picked['source']['name'], $picked['detected_by']]);
        $this->patchJson("/api/v1/supplier-returns/bin/{$unknown['id']}", ['source' => ['type' => 'supplier', 'id' => $this->supplierB.'0']])->assertUnprocessable();

        // Selecting lines of two sources makes two notes, numbered per shop.
        $ids = BinItem::withoutGlobalScopes()->pluck('id')->all();
        $notes = $this->postJson('/api/v1/supplier-returns/notes', ['item_ids' => $ids])->assertCreated()->json('data');
        $this->assertEqualsCanonicalizing(['SRN-00001', 'SRN-00002'], array_column($notes, 'reference'));
        $this->assertEqualsCanonicalizing([[7, 140000], [2, 8000]], array_map(fn ($n) => [$n['units'], $n['total_cost']], $notes));
        $this->assertSame([], $this->bin()['groups']);
    }

    public function test_a_serial_tells_its_supplier_exactly(): void
    {
        $this->buy($this->supplierB, $this->phone, 2, 600000, ['351234567890121', '351234567890139']);
        // A later lot from supplier A: FIFO alone would guess wrong for units from the first one.
        $this->buy($this->supplierA, $this->phone, 1, 610000, ['351234567890147']);

        $this->toBin(['variant_id' => $this->phone, 'qty' => 1])->assertUnprocessable()->assertJsonPath('code', 'serials_required');
        $row = $this->toBin(['variant_id' => $this->phone, 'qty' => 1, 'serials' => ['35-123456-789014-7']])->assertCreated()->json('data.0');

        $this->assertSame([$this->supplierA, 'serial', '351234567890147', 610000], [$row['source']['id'], $row['detected_by'], $row['serial'], $row['unit_cost']]);
        $this->assertSame(SerialNumber::DAMAGED, $this->serialStatus('351234567890147'));
        $this->assertSame(2, $this->stock($this->phone));
        // Can't be sold now.
        $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $this->phone, 'qty' => 1, 'serials' => ['351234567890147']]],
            'payments' => [['method' => 'cash', 'amount' => 700000]],
        ])->assertUnprocessable();
    }

    public function test_a_damaged_customer_return_goes_to_the_bin_with_the_source_of_the_lot_it_was_sold_from(): void
    {
        $this->buy($this->supplierA, $this->screen, 3, 20000);
        $sale = $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $this->screen, 'qty' => 2]],
            'payments' => [['method' => 'cash', 'amount' => 80000]],
        ])->assertCreated()->json('data');

        $this->postJson("/api/v1/sales/{$sale['id']}/returns", [
            'refund_method' => 'cash',
            'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => false, 'defect_reason' => 'not_working']],
        ])->assertCreated();

        $this->assertSame(1, $this->stock($this->screen), 'a damaged unit is not restocked');
        $group = $this->bin()['groups'][0];
        $this->assertSame(['مورد الشاشات', 1, 20000], [$group['source']['name'], $group['units'], $group['value']]);
        $this->assertSame(['sale_return', 'lot', 'not_working'], [$group['items'][0]['origin'], $group['items'][0]['detected_by'], $group['items'][0]['reason']]);
        $this->assertStringContainsString('RET-00001', $group['items'][0]['origin_label']);
    }

    public function test_a_defective_repair_part_goes_to_the_bin(): void
    {
        $this->buy($this->supplierA, $this->screen, 2, 20000);
        $ticket = $this->postJson('/api/v1/repairs/tickets', [
            'customer_name' => 'محمود', 'customer_phone' => '01234567890', 'device_name' => 'Samsung A54', 'reported_note' => 'الشاشة مش شغالة',
        ])->assertCreated()->json('data');
        $url = "/api/v1/repairs/tickets/{$ticket['id']}";
        $this->postJson("{$url}/parts", ['variant_id' => $this->screen, 'qty' => 1])->assertOk();
        $part = $this->getJson($url)->json('data.parts.0');

        $this->deleteJson("{$url}/parts/{$part['id']}?defective=1&reason=defect")->assertOk();

        $this->assertSame(1, $this->stock($this->screen), 'the bad part does not go back to stock');
        $item = $this->bin()['groups'][0]['items'][0];
        $this->assertSame(['repair', $this->supplierA, 'عيب صناعة'], [$item['origin'], $item['source']['id'], $item['reason_label']]);
    }

    public function test_partial_acceptance_credits_the_supplier_and_restocks_the_rest_into_its_lot(): void
    {
        $this->buy($this->supplierA, $this->screen, 5, 20000);
        $this->assertSame(100000, $this->balance($this->supplierA));
        $row = $this->toBin(['variant_id' => $this->screen, 'qty' => 3])->json('data.0');
        $note = $this->note([$row['id']]);
        $this->assertSame(['pending', 'مورد الشاشات', '01011111111'], [$note['status'], $note['source']['name'], $note['source']['phone']]);

        $this->postJson("/api/v1/supplier-returns/notes/{$note['id']}/send")->assertOk()->assertJsonPath('data.status', 'sent');
        $this->postJson("/api/v1/supplier-returns/notes/{$note['id']}/send")->assertUnprocessable();

        $this->postJson("/api/v1/supplier-returns/notes/{$note['id']}/settle", ['items' => [['id' => $row['id'], 'accepted_qty' => 2]], 'resolution' => 'credit'])
            ->assertUnprocessable()->assertJsonPath('code', 'rejected_action_required');
        $settled = $this->postJson("/api/v1/supplier-returns/notes/{$note['id']}/settle", [
            'items' => [['id' => $row['id'], 'accepted_qty' => 2]],
            'resolution' => 'credit',
            'rejected_action' => 'restock',
        ])->assertOk()->json('data');

        $this->assertSame(['partially_accepted', 40000, 20000], [$settled['status'], $settled['accepted_value'], $settled['rejected_value']]);
        $this->assertSame(60000, $this->balance($this->supplierA), 'the accepted value comes off what the shop owes');
        $this->assertSame(3, $this->stock($this->screen), 'the refused unit is sellable again');
        $lot = $this->inShop(fn () => StockLot::query()->where('variant_id', $this->screen)->sole());
        $this->assertSame(3, $lot->qty_remaining, 'back into the lot it left');
        $this->assertSame(['partial', 2], [$settled['items'][0]['outcome'], $settled['items'][0]['accepted_qty']]);
        $this->assertTrue($this->inShop(fn () => AuditEntry::query()->where('action', 'supplier_returns.accepted')->exists()));
        $this->assertTrue($this->inShop(fn () => AuditEntry::query()->where('action', 'supplier_returns.rejected')->exists()));
        $this->postJson("/api/v1/supplier-returns/notes/{$note['id']}/settle", ['items' => []])->assertUnprocessable()->assertJsonPath('code', 'note_closed');

        $statement = $this->getJson("/api/v1/suppliers/{$this->supplierA}/statement")->json('data');
        $this->assertContains('return_note', array_column($statement, 'type'));
    }

    public function test_a_cash_refund_goes_into_the_drawer_and_leaves_the_balance(): void
    {
        $this->buy($this->supplierA, $this->screen, 2, 20000);
        $before = $this->getJson('/api/v1/cash/current')->json('data.expected.cash');
        $row = $this->toBin(['variant_id' => $this->screen, 'qty' => 2])->json('data.0');
        $note = $this->note([$row['id']]);

        $this->postJson("/api/v1/supplier-returns/notes/{$note['id']}/settle", ['items' => [['id' => $row['id'], 'accepted_qty' => 2]], 'resolution' => 'refund'])
            ->assertUnprocessable()->assertJsonPath('code', 'refund_method_required');
        $this->postJson("/api/v1/supplier-returns/notes/{$note['id']}/settle", [
            'items' => [['id' => $row['id'], 'accepted_qty' => 2]], 'resolution' => 'refund', 'refund_method' => 'cash',
        ])->assertOk()->assertJsonPath('data.status', 'accepted');

        $this->assertSame($before + 40000, $this->getJson('/api/v1/cash/current')->json('data.expected.cash'));
        $this->assertSame(40000, $this->balance($this->supplierA), 'returned and paid back: what is owed does not move');
        $this->assertSame(['refund', 'return_note', 'purchase'], array_column($this->getJson("/api/v1/suppliers/{$this->supplierA}/statement")->json('data'), 'type'));
        $this->assertSame(0, $this->stock($this->screen));
    }

    public function test_a_replacement_comes_back_into_stock_with_its_new_serial(): void
    {
        $this->buy($this->supplierB, $this->phone, 1, 600000, ['351234567890121']);
        $row = $this->toBin(['variant_id' => $this->phone, 'qty' => 1, 'serials' => ['351234567890121']])->json('data.0');
        $note = $this->note([$row['id']]);

        $this->postJson("/api/v1/supplier-returns/notes/{$note['id']}/settle", ['items' => [['id' => $row['id'], 'accepted_qty' => 1]], 'resolution' => 'replacement'])
            ->assertUnprocessable()->assertJsonPath('code', 'serials_required');
        $this->postJson("/api/v1/supplier-returns/notes/{$note['id']}/settle", [
            'items' => [['id' => $row['id'], 'accepted_qty' => 1, 'replacement_serials' => ['359999999999999']]], 'resolution' => 'replacement',
        ])->assertOk();

        $this->assertSame(1, $this->stock($this->phone));
        $this->assertSame(SerialNumber::OUT, $this->serialStatus('351234567890121'));
        $this->assertSame(SerialNumber::IN_STOCK, $this->serialStatus('359999999999999'));
        $this->assertSame(600000, $this->balance($this->supplierB), 'a replacement does not touch the account');

        // The replacement's own source is known: the note's supplier.
        $again = $this->toBin(['variant_id' => $this->phone, 'qty' => 1, 'serials' => ['359999999999999']])->json('data.0');
        $this->assertSame([$this->supplierB, 'serial', 'SRN-00001'], [$again['source']['id'], $again['detected_by'], $again['source_doc']]);
    }

    public function test_a_refused_note_can_be_written_off_and_the_bin_can_restock_or_write_off_a_unit(): void
    {
        $this->buy($this->supplierB, $this->phone, 3, 600000, ['351234567890121', '351234567890139', '351234567890147']);
        [$a, $b, $c] = array_map(fn ($s) => $this->toBin(['variant_id' => $this->phone, 'qty' => 1, 'serials' => [$s]])->json('data.0'), ['351234567890121', '351234567890139', '351234567890147']);

        $note = $this->note([$a['id']]);
        $this->postJson("/api/v1/supplier-returns/notes/{$note['id']}/settle", ['items' => [], 'rejected_action' => 'write_off'])
            ->assertOk()->assertJsonPath('data.status', 'rejected');
        $this->assertSame(SerialNumber::OUT, $this->serialStatus('351234567890121'));
        $this->assertTrue($this->inShop(fn () => AuditEntry::query()->where('action', 'supplier_returns.written_off')->exists()));
        $this->assertSame(1800000, $this->balance($this->supplierB));

        $this->postJson("/api/v1/supplier-returns/bin/{$b['id']}/restock")->assertOk()->assertJsonPath('data.outcome', 'restocked');
        $this->assertSame(SerialNumber::IN_STOCK, $this->serialStatus('351234567890139'));
        $this->assertSame(1, $this->stock($this->phone));
        $this->postJson("/api/v1/supplier-returns/bin/{$b['id']}/restock")->assertUnprocessable()->assertJsonPath('code', 'not_in_bin');

        $this->postJson("/api/v1/supplier-returns/bin/{$c['id']}/write-off")->assertOk()->assertJsonPath('data.outcome', 'written_off');
        $this->assertSame(SerialNumber::OUT, $this->serialStatus('351234567890147'));
        $this->assertSame(1, $this->stock($this->phone));
    }

    public function test_a_cancelled_note_puts_its_units_back_in_the_bin(): void
    {
        $this->buy($this->supplierA, $this->screen, 2, 20000);
        $row = $this->toBin(['variant_id' => $this->screen, 'qty' => 1])->json('data.0');
        $note = $this->note([$row['id']]);
        $this->postJson('/api/v1/supplier-returns/notes', ['item_ids' => [$row['id']]])->assertUnprocessable()->assertJsonPath('code', 'not_in_bin');

        $this->postJson("/api/v1/supplier-returns/notes/{$note['id']}/cancel")->assertOk()->assertJsonPath('data.status', 'cancelled');
        $this->assertSame(1, $this->bin()['units']);
        $this->getJson('/api/v1/supplier-returns/notes?status=open')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/supplier-returns/notes/{$note['id']}")->assertOk()->assertJsonCount(0, 'data.items');
    }

    public function test_permissions_and_costs(): void
    {
        $this->buy($this->supplierA, $this->screen, 2, 20000);
        $cashier = $this->staff('cashier');
        $storekeeper = $this->staff('storekeeper');

        Sanctum::actingAs($cashier);
        $this->getJson('/api/v1/supplier-returns/bin')->assertForbidden();
        $this->toBin(['variant_id' => $this->screen, 'qty' => 1])->assertForbidden();

        Sanctum::actingAs($storekeeper);
        $this->toBin(['variant_id' => $this->screen, 'qty' => 1])->assertCreated()->assertJsonPath('data.0.unit_cost', 20000);
        $this->getJson('/api/v1/supplier-returns/bin')->assertOk();
    }

    public function test_without_the_module_a_damaged_return_stays_out_of_the_bin(): void
    {
        $this->postJson('/api/v1/modules/supplier_returns/disable')->assertOk();
        $this->buy($this->supplierA, $this->screen, 1, 20000);
        $sale = $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $this->screen, 'qty' => 1]],
            'payments' => [['method' => 'cash', 'amount' => 40000]],
        ])->assertCreated()->json('data');
        $this->postJson("/api/v1/sales/{$sale['id']}/returns", [
            'refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => false]],
        ])->assertCreated();

        $this->assertSame(0, BinItem::withoutGlobalScopes()->count());
        $this->getJson('/api/v1/supplier-returns/bin')->assertForbidden()->assertJsonPath('code', 'module_not_enabled');
    }

    public function test_the_whatsapp_template_and_the_reports(): void
    {
        $template = collect($this->getJson('/api/v1/messages/templates')->assertOk()->json('data'))->firstWhere('key', 'supplier_return_note');
        $this->assertSame('الموردين', $template['group_label']);

        $this->buy($this->supplierA, $this->screen, 10, 20000);
        $this->buy($this->supplierB, $this->v[0], 20, 3500);
        $row = $this->toBin(['variant_id' => $this->screen, 'qty' => 2])->json('data.0');
        $this->toBin(['variant_id' => $this->screen, 'qty' => 1]);
        $this->note([$row['id']]);

        $rate = $this->getJson('/api/v1/reports/supplier_return_rate')->assertOk()->json('data');
        $rows = collect($rate['rows'])->keyBy('name');
        $this->assertSame([10, 200000, 3, 60000, 30], [
            $rows['مورد الشاشات']['bought_qty'], $rows['مورد الشاشات']['bought_value'], $rows['مورد الشاشات']['returned_qty'], $rows['مورد الشاشات']['returned_value'], (int) $rows['مورد الشاشات']['rate'],
        ]);
        $this->assertEquals(0, $rows['موزع سامسونج']['rate']);

        $open = $this->getJson('/api/v1/reports/supplier_returns_open')->assertOk()->json('data');
        $this->assertSame([['مورد الشاشات', 1, 2, 3, 60000, 60000, 0]], array_map(
            fn ($r) => [$r['source'], $r['in_bin'], $r['on_note'], $r['units'], $r['week'], $r['value'], $r['older']],
            $open['rows'],
        ));
    }
}
