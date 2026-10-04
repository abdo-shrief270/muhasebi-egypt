<?php

namespace Tests\Feature\Repairs;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Repairs\Events\TicketDelivered;
use App\Modules\Repairs\Models\FaultType;
use App\Modules\Repairs\Models\RepairTicket;
use App\Support\Audit\AuditEntry;
use App\Support\Events\StoredEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class RepairTicketsTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** @var list<int> */
    private array $faults;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->openShift(10000);
        $this->faults = $this->inShop(fn () => FaultType::query()->whereIn('name', ['كسر زجاج', 'مش بيشحن'])->orderBy('id')->pluck('id')->all());
    }

    private function receive(array $overrides = [])
    {
        return $this->postJson('/api/v1/repairs/tickets', [
            'customer_name' => 'محمود',
            'customer_phone' => '01234567890',
            'device_name' => 'Samsung Galaxy A54',
            'imei' => '356789012345678',
            'unlock_type' => 'pin',
            'unlock_code' => '1379',
            'accessories' => ['case', 'sim'],
            'condition' => ['scratches'],
            'checks' => ['powers_on' => 'yes', 'touch' => 'no'],
            'fault_ids' => $this->faults,
            'reported_note' => 'وقع منه',
            'expected_at' => now()->addDay()->toIso8601String(),
            'estimate' => 80000,
            'deposits' => [['method' => 'cash', 'amount' => 20000]],
            ...$overrides,
        ]);
    }

    private function stock(string $variantId): int
    {
        return $this->inShop(fn () => app(StockLedger::class)->quantity($this->branchId, $variantId));
    }

    public function test_the_options_put_what_the_shop_receives_most_first(): void
    {
        $this->receive(['device_name' => 'iPhone 11', 'fault_ids' => [$this->faults[1]], 'deposits' => []])->assertCreated();
        $this->receive(['device_name' => 'iPhone 11', 'fault_ids' => [$this->faults[1]], 'deposits' => []])->assertCreated();
        $this->receive(['deposits' => []])->assertCreated();

        $options = $this->getJson('/api/v1/repairs/options')->assertOk()->json('data');
        $this->assertSame(['iPhone 11', 'Samsung Galaxy A54'], array_column($options['recent_devices'], 'device_name'));
        $this->assertSame([$this->faults[1], $this->faults[0]], $options['top_faults']);
    }

    public function test_intake_records_the_device_customer_faults_and_deposit(): void
    {
        $ticket = $this->receive()->assertCreated()->json('data');

        $this->assertSame(['RP-00001', 'received', 'محمود', '+201234567890'], [$ticket['reference'], $ticket['status'], $ticket['customer_name'], $ticket['customer_phone']]);
        $this->assertSame(['كسر زجاج', 'مش بيشحن'], array_column($ticket['reported_faults'], 'name'));
        $this->assertSame(['جراب', 'شريحة'], $ticket['accessories_labels']);
        $this->assertSame([20000, 0, -20000], [$ticket['paid'], $ticket['total'], $ticket['due']]);
        $this->assertSame('1379', $ticket['unlock_code'], 'the owner works on devices');
        $this->assertSame('1379', $this->inShop(fn () => RepairTicket::query()->firstOrFail()->unlock_code));
        $this->assertNotSame('1379', \DB::table('repair_tickets')->value('unlock_code'), 'stored encrypted');

        // The customer was added by phone, and is found again by phone next time.
        $this->assertSame(1, $this->getJson('/api/v1/customers?q=01234567890')->json('meta.total'));
        $again = $this->receive(['customer_name' => 'اسم تاني', 'deposits' => []])->json('data');
        $this->assertSame($ticket['customer_id'], $again['customer_id']);

        $this->assertSame(10000 + 20000, $this->getJson('/api/v1/cash/current')->json('data.expected.cash'), 'the deposit is in the drawer');
        $this->assertSame(['received', 'payment'], array_column($this->getJson("/api/v1/repairs/tickets/{$ticket['id']}")->json('data.events'), 'type'));

        $this->receive(['fault_ids' => [], 'reported_note' => null])->assertUnprocessable()->assertJsonValidationErrors('fault_ids');
        $this->receive(['customer_phone' => '123'])->assertUnprocessable()->assertJsonPath('code', 'customer_phone_invalid');
    }

    public function test_repair_flow_parts_labor_and_delivery_with_warranty(): void
    {
        $id = $this->receive()->json('data.id');
        $url = "/api/v1/repairs/tickets/{$id}";

        $this->postJson("{$url}/status", ['status' => 'ready'])->assertUnprocessable()->assertJsonPath('code', 'status_not_allowed');
        $this->postJson("{$url}/status", ['status' => 'diagnosing'])->assertOk();
        $this->patchJson($url, ['diagnosed_fault_ids' => [$this->faults[0]], 'diagnosis_note' => 'الشاشة بس', 'labor' => 30000])->assertOk();
        $this->postJson("{$url}/status", ['status' => 'repairing', 'note' => 'العميل وافق'])->assertOk();

        // A charger (450 ج, cost 300) fitted, then a second one taken back off.
        $this->postJson("{$url}/parts", ['variant_id' => $this->v[1], 'qty' => 2])->assertOk();
        $this->assertSame(18, $this->stock($this->v[1]));
        $ticket = $this->getJson($url)->json('data');
        $this->assertSame([90000, 60000, 120000], [$ticket['parts_total'], $ticket['parts_cost'], $ticket['total']]);
        $this->deleteJson("{$url}/parts/{$ticket['parts'][0]['id']}")->assertOk();
        $this->assertSame(20, $this->stock($this->v[1]));
        $this->postJson("{$url}/parts", ['variant_id' => $this->v[1], 'qty' => 1, 'unit_price' => 40000])->assertOk();

        $this->postJson("{$url}/deliver", [])->assertUnprocessable()->assertJsonPath('code', 'ticket_not_ready');
        $this->postJson("{$url}/status", ['status' => 'ready'])->assertOk()->assertJsonPath('data.status', 'ready');

        // 300 labor + 400 part = 700, 200 deposit → 500 due; 600 cash gives 100 change.
        $this->postJson("{$url}/deliver", ['payments' => [['method' => 'cash', 'amount' => 40000]]])->assertUnprocessable()->assertJsonPath('code', 'underpaid');
        $done = $this->postJson("{$url}/deliver", ['payments' => [['method' => 'cash', 'amount' => 60000]], 'warranty_days' => 30])->assertOk()->json('data');
        $this->assertSame(['delivered', 70000, 70000, 0, true], [$done['status'], $done['total'], $done['paid'], $done['due'], $done['under_warranty']]);
        $this->assertSame(10000 + 20000 + 50000, $this->getJson('/api/v1/cash/current')->json('data.expected.cash'));

        $this->patchJson($url, ['labor' => 1])->assertUnprocessable()->assertJsonPath('code', 'ticket_closed');
        $this->assertTrue(StoredEvent::query()->where('name', TicketDelivered::NAME)->exists());
        $this->assertSame(1, AuditEntry::query()->where('action', 'repairs.delivered')->count());
        $this->assertContains('part_removed', array_column($done['events'], 'type'));

        // Back within the warranty: a new ticket linked to this one.
        $back = $this->postJson("{$url}/warranty", ['note' => 'الشاشة فصلت'])->assertCreated()->json('data');
        $this->assertSame([$id, 'received', ['كسر زجاج']], [$back['warranty_of_id'], $back['status'], array_column($back['reported_faults'], 'name')]);
    }

    public function test_a_rejected_device_goes_back_with_its_deposit(): void
    {
        $id = $this->receive()->json('data.id');
        $this->postJson("/api/v1/repairs/tickets/{$id}/status", ['status' => 'rejected', 'note' => 'العميل رفض السعر'])->assertOk();

        $done = $this->postJson("/api/v1/repairs/tickets/{$id}/deliver", [])->assertOk()->json('data');
        $this->assertSame([0, 0, false], [$done['paid'], $done['total'], $done['under_warranty']]);
        $this->assertSame(10000, $this->getJson('/api/v1/cash/current')->json('data.expected.cash'), 'the deposit came back out of the drawer');
        $this->assertSame(['deposit', 'refund'], array_column($done['payments'], 'kind'));
        $this->postJson("/api/v1/repairs/tickets/{$id}/warranty")->assertUnprocessable()->assertJsonPath('code', 'warranty_expired');
    }

    public function test_the_rest_of_the_bill_on_the_customers_account(): void
    {
        $id = $this->receive(['deposits' => []])->json('data.id');
        $url = "/api/v1/repairs/tickets/{$id}";
        $this->postJson("{$url}/status", ['status' => 'repairing'])->assertOk();
        $this->patchJson($url, ['labor' => 50000])->assertOk();
        $this->postJson("{$url}/status", ['status' => 'ready'])->assertOk();

        $done = $this->postJson("{$url}/deliver", ['payments' => [['method' => 'cash', 'amount' => 10000], ['method' => 'credit', 'amount' => 40000]]])->assertOk()->json('data');
        $this->assertSame([10000, 40000], [$done['paid'], $done['credit']]);
        $customer = $this->getJson("/api/v1/customers/{$done['customer_id']}")->json('data');
        $this->assertSame(40000, $customer['balance']);
        $this->assertSame('repair', $this->getJson("/api/v1/customers/{$done['customer_id']}/statement")->json('data.0.type'));
    }

    public function test_the_public_tracking_link(): void
    {
        $ticket = $this->receive()->json('data');
        auth()->forgetGuards();
        $this->app['auth']->guard('sanctum')->forgetUser();

        $public = $this->getJson("/api/v1/public/repairs/{$ticket['public_token']}")->assertOk()->json('data');
        $this->assertSame(['RP-00001', 'مستلم', '5678'], [$public['reference'], $public['status_label'], $public['imei_tail']]);
        $this->assertArrayNotHasKey('unlock_code', $public);
        $this->assertArrayNotHasKey('customer_phone', $public);
        $this->getJson('/api/v1/public/repairs/'.str_repeat('x', 24))->assertNotFound();
    }

    public function test_lists_filters_and_summary(): void
    {
        $late = $this->receive(['expected_at' => now()->subHour()->toIso8601String(), 'deposits' => []])->json('data');
        $this->receive(['customer_name' => 'سلمى', 'customer_phone' => '01099990000', 'device_name' => 'iPhone 13', 'imei' => null, 'deposits' => []]);

        $this->assertSame([2, 1], [$this->getJson('/api/v1/repairs/summary')->json('data.open'), $this->getJson('/api/v1/repairs/summary')->json('data.overdue')]);
        $this->assertSame([$late['id']], array_column($this->getJson('/api/v1/repairs/tickets?overdue=1')->json('data'), 'id'));
        $this->assertSame(['iPhone 13'], array_column($this->getJson('/api/v1/repairs/tickets?q=01099990000')->json('data'), 'device_name'));
        $this->assertSame(['iPhone 13'], array_column($this->getJson('/api/v1/repairs/tickets?q=iphone')->json('data'), 'device_name'));
        $this->assertCount(1, $this->getJson('/api/v1/repairs/tickets?q=RP-00001')->json('data'));
        $this->assertTrue($late['is_overdue']);
    }

    public function test_who_may_do_what(): void
    {
        $id = $this->receive()->json('data.id');
        $url = "/api/v1/repairs/tickets/{$id}";

        $technician = $this->staff('technician');
        $cashier = $this->staff('cashier');
        Sanctum::actingAs($technician);
        $this->assertSame('1379', $this->getJson($url)->json('data.unlock_code'));
        $this->postJson("{$url}/status", ['status' => 'repairing'])->assertOk();
        $this->patchJson($url, ['labor' => 10000, 'technician_id' => $technician->id])->assertOk()->assertJsonPath('data.technician_name', 'technician');
        $this->patchJson($url, ['discount' => 100])->assertForbidden();
        $this->postJson("{$url}/status", ['status' => 'ready'])->assertOk();
        $this->postJson("{$url}/deliver", [])->assertForbidden();
        $this->assertContains($technician->id, array_column($this->getJson('/api/v1/repairs/options')->json('data.technicians'), 'id'));

        Sanctum::actingAs($cashier);
        $this->openShift();
        $this->assertNull($this->getJson($url)->json('data.unlock_code'), 'the cashier never sees the unlock code');
        $this->postJson("{$url}/status", ['status' => 'repairing'])->assertForbidden();
        $this->postJson("{$url}/parts", ['variant_id' => $this->v[1], 'qty' => 1])->assertForbidden();
        $this->receive(['deposits' => []])->assertCreated();
        $this->postJson("{$url}/deliver", [])->assertOk()->assertJsonPath('data.status', 'delivered');
        $this->patchJson($url, ['technician_id' => '01999999-0000-7000-8000-000000000000'])->assertForbidden();
    }

    public function test_fault_lists_can_be_edited(): void
    {
        $category = $this->postJson('/api/v1/repairs/fault-categories', ['name' => 'الشبكة'])->assertCreated()->json('data');
        $this->postJson('/api/v1/repairs/fault-categories', ['name' => 'الشبكة'])->assertUnprocessable();
        $withType = $this->postJson("/api/v1/repairs/fault-categories/{$category['id']}/types", ['name' => 'مفيش إشارة', 'default_labor_price' => 15000])->assertCreated()->json('data');
        $type = $withType['types'][0];

        $this->patchJson("/api/v1/repairs/fault-types/{$type['id']}", ['is_active' => false])->assertOk()->assertJsonPath('data.types.0.is_active', false);

        $ticket = $this->receive(['fault_ids' => [$type['id']], 'deposits' => []])->json('data');
        $this->assertSame(15000, $this->getJson("/api/v1/repairs/tickets/{$ticket['id']}")->json('data.suggested_labor'));

        Sanctum::actingAs($this->staff('cashier'));
        $this->postJson('/api/v1/repairs/fault-categories', ['name' => 'x'])->assertForbidden();
    }

    public function test_repairs_need_the_module_and_stay_in_the_shop_and_branch(): void
    {
        $ticket = $this->receive()->json('data');

        Sanctum::actingAs($this->registerShop(ShopType::Accessories));
        $this->getJson('/api/v1/repairs/tickets')->assertForbidden()->assertJsonPath('code', 'module_not_enabled');

        Sanctum::actingAs($this->registerShop(ShopType::Repair));
        $this->getJson("/api/v1/repairs/tickets/{$ticket['id']}")->assertNotFound();
    }
}
