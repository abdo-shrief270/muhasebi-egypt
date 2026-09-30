<?php

namespace Tests\Feature\Customers;

use App\Modules\Cash\Models\CashMovement;
use App\Modules\Customers\Models\Customer;
use App\Modules\Messaging\Models\MessageLog;
use App\Modules\Repairs\Models\FaultType;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Sales\Models\Sale;
use App\Support\Audit\AuditEntry;
use App\Support\Events\EventRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** Personal Data Protection Law 151/2020: consent, erasure, export and retention of customer data. */
class CustomerPrivacyTest extends TestCase
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
        return $this->postJson('/api/v1/customers', ['name' => 'أحمد علي', 'phone' => '01012345678', 'consent' => true, ...$data])->assertCreated()->json('data');
    }

    private function receive(array $overrides = []): array
    {
        return $this->postJson('/api/v1/repairs/tickets', [
            'customer_name' => 'محمود',
            'customer_phone' => '01234567890',
            'consent' => true,
            'device_name' => 'Samsung Galaxy A54',
            'imei' => '356789012345678',
            'unlock_type' => 'pin',
            'unlock_code' => '1379',
            'fault_ids' => $this->inShop(fn () => FaultType::query()->limit(1)->pluck('id')->all()),
            ...$overrides,
        ])->assertCreated()->json('data');
    }

    /** Receives, then hands back unrepaired (nothing to pay). */
    private function receiveAndReturn(array $overrides = []): array
    {
        $ticket = $this->receive($overrides);
        $this->postJson("/api/v1/repairs/tickets/{$ticket['id']}/status", ['status' => 'rejected'])->assertOk();
        $this->postJson("/api/v1/repairs/tickets/{$ticket['id']}/deliver", [])->assertOk();

        return $ticket;
    }

    private function relay(): void
    {
        app(EventRelay::class)->publishPending();
    }

    public function test_consent_is_recorded_with_who_and_when(): void
    {
        $agreed = $this->customer();
        $this->assertTrue($agreed['data_consent']);
        $this->assertNotNull($agreed['data_consent_at']);
        $this->assertSame('المالك', $agreed['data_consent_by_name']);

        $declined = $this->customer(['name' => 'منى', 'phone' => '01198765432', 'consent' => false]);
        $this->assertFalse($declined['data_consent']);

        // Added without asking (older screens / imports): unknown until someone asks.
        $unknown = $this->customer(['name' => 'قديم', 'phone' => null, 'consent' => null]);
        $this->assertNull($unknown['data_consent']);
        $this->assertNull($unknown['data_consent_at']);
        $asked = $this->patchJson("/api/v1/customers/{$unknown['id']}", ['consent' => true])->assertOk()->json('data');
        $this->assertTrue($asked['data_consent']);
        $this->assertSame(1, AuditEntry::query()->where('action', 'customers.consent_recorded')->count());

        // Repair intake: a typed-in customer is added with their consent.
        $ticket = $this->receive();
        $this->assertTrue($this->getJson("/api/v1/customers/{$ticket['customer_id']}")->json('data.data_consent'));
    }

    public function test_erasure_anonymises_every_copy_and_keeps_the_money(): void
    {
        $customer = $this->customer();
        $sale = $this->sell([['method' => 'cash', 'amount' => 10000], ['method' => 'credit', 'amount' => 35000]], ['customer_id' => $customer['id']])->assertCreated()->json('data');
        $this->postJson("/api/v1/customers/{$customer['id']}/payments", ['amount' => 35000, 'payment_method' => 'cash'])->assertCreated();
        // A walk-in receipt typed with the same phone, and a repair of theirs.
        $typed = $this->sell([['method' => 'cash', 'amount' => 45000]], ['customer_name' => 'أحمد', 'customer_phone' => '+201012345678'])->assertCreated()->json('data');
        $ticket = $this->receiveAndReturn(['customer_id' => $customer['id'], 'customer_name' => null, 'customer_phone' => null]);
        // WhatsApp messages opened for them.
        $this->postJson('/api/v1/messages/log', ['template' => 'sale_receipt', 'phone' => '+201012345678', 'subject_type' => 'sale', 'subject_id' => $sale['id']])->assertCreated();
        $this->postJson('/api/v1/messages/log', ['template' => 'sale_receipt', 'phone' => '+201099999999', 'subject_type' => 'sale', 'subject_id' => $sale['id']])->assertCreated();
        // Someone else's data stays.
        $other = $this->customer(['name' => 'منى', 'phone' => '01198765432']);
        $otherSale = $this->sell([['method' => 'cash', 'amount' => 45000]], ['customer_id' => $other['id']])->assertCreated()->json('data');

        $erased = $this->postJson("/api/v1/customers/{$customer['id']}/erase")->assertOk()->json('data');
        $this->assertSame(['عميل محذوف', null, false, 0], [$erased['name'], $erased['phone'], $erased['is_active'], $erased['balance']]);
        $this->assertNotNull($erased['erased_at']);

        // The account statement and the drawer keep their amounts; the drawer note loses the name.
        $this->assertSame([-35000, 35000], array_column($this->getJson("/api/v1/customers/{$customer['id']}/statement")->json('data'), 'amount'));
        $notes = $this->inShop(fn () => CashMovement::query()->where('ref_type', 'customer_transaction')->pluck('note')->all());
        $this->assertSame(['تحصيل من عميل محذوف'], $notes);
        $this->assertSame(0, $this->inShop(fn () => AuditEntry::query()->where('description', 'like', '%أحمد علي%')->count()), 'the name is gone from the audit log');
        $this->assertSame(1, AuditEntry::query()->where('action', 'customers.erased')->count());

        $this->relay();

        $sales = $this->inShop(fn () => Sale::query()->get()->keyBy('id'));
        foreach ([$sale['id'], $typed['id']] as $id) {
            $this->assertSame(['عميل محذوف', null], [$sales[$id]->customer_name, $sales[$id]->customer_phone]);
        }
        $this->assertSame([45000, 'منى', '+201198765432'], [$sales[$sale['id']]->total, $sales[$otherSale['id']]->customer_name, $sales[$otherSale['id']]->customer_phone]);

        $repair = $this->inShop(fn () => RepairTicket::query()->findOrFail($ticket['id']));
        $this->assertSame(['عميل محذوف', null, null, 'none'], [$repair->customer_name, $repair->customer_phone, $repair->unlock_code, $repair->unlock_type]);
        $this->assertSame('356789012345678', $repair->imei, 'the IMEI identifies the device, not the person');
        $this->assertSame('delivered', $repair->status->value);

        $phones = $this->inShop(fn () => MessageLog::query()->orderBy('seq')->pluck('phone')->all());
        $this->assertSame([null, '+201099999999'], $phones);

        // Nothing personal in the outbox either.
        $this->assertStringNotContainsString('1012345678', (string) DB::table('domain_events')->where('name', 'customers.erased')->value('payload'));

        // Idempotent; and an erased customer can't be edited back.
        $this->postJson("/api/v1/customers/{$customer['id']}/erase")->assertOk()->assertJsonPath('data.name', 'عميل محذوف');
        $this->assertSame(1, AuditEntry::query()->where('action', 'customers.erased')->count());
        $this->patchJson("/api/v1/customers/{$customer['id']}", ['name' => 'أحمد'])->assertUnprocessable()->assertJsonPath('code', 'customer_erased');
        $this->sell([['method' => 'credit', 'amount' => 45000]], ['customer_id' => $customer['id']])->assertUnprocessable()->assertJsonPath('code', 'customer_inactive');
    }

    public function test_erasure_is_refused_with_a_balance_or_a_device_in_the_shop(): void
    {
        $owing = $this->customer(['opening_balance' => 20000]);
        $this->postJson("/api/v1/customers/{$owing['id']}/erase")->assertUnprocessable()->assertJsonPath('code', 'customer_has_balance');

        $credit = $this->customer(['name' => 'رصيد', 'phone' => '01111111111', 'opening_balance' => -5000]);
        $this->postJson("/api/v1/customers/{$credit['id']}/erase")->assertUnprocessable()->assertJsonPath('code', 'customer_has_balance');

        $ticket = $this->receive();
        $this->postJson("/api/v1/customers/{$ticket['customer_id']}/erase")->assertUnprocessable()->assertJsonPath('code', 'customer_has_open_repairs');

        $this->assertSame('أحمد علي', $this->getJson("/api/v1/customers/{$owing['id']}")->json('data.name'));
        $this->assertSame(0, AuditEntry::query()->where('action', 'customers.erased')->count());
    }

    public function test_only_the_owner_erases_and_managers_export(): void
    {
        $customer = $this->customer();
        $technician = $this->staff('technician');
        $cashier = $this->staff('cashier');
        $manager = $this->staff('manager');

        // The cashier manages customers but can't take their whole data home.
        Sanctum::actingAs($cashier);
        $this->getJson("/api/v1/customers/{$customer['id']}/export")->assertForbidden();

        Sanctum::actingAs($technician);
        $this->postJson("/api/v1/customers/{$customer['id']}/erase")->assertForbidden();
        $this->getJson("/api/v1/customers/{$customer['id']}/export")->assertForbidden();
        $this->getJson('/api/v1/customers/privacy-settings')->assertForbidden();

        Sanctum::actingAs($manager);
        $this->postJson("/api/v1/customers/{$customer['id']}/erase")->assertForbidden();
        $this->putJson('/api/v1/customers/privacy-settings', ['retention_years' => 2])->assertForbidden();
        $this->getJson("/api/v1/customers/{$customer['id']}/export")->assertOk();
    }

    public function test_export_holds_the_profile_ledger_sales_and_repairs(): void
    {
        $customer = $this->customer(['opening_balance' => 5000]);
        $this->sell([['method' => 'cash', 'amount' => 45000]], ['customer_id' => $customer['id']])->assertCreated();
        $this->receive(['customer_id' => $customer['id'], 'customer_name' => null, 'customer_phone' => null]);

        $response = $this->get("/api/v1/customers/{$customer['id']}/export")->assertOk();
        $this->assertStringContainsString('attachment; filename="customer-'.$customer['id'].'.json"', (string) $response->headers->get('Content-Disposition'));
        $data = $response->json();

        $this->assertSame(['أحمد علي', '+201012345678', true, 5000], [$data['customer']['name'], $data['customer']['phone'], $data['customer']['data_consent'], $data['customer']['balance']]);
        $this->assertSame([5000], array_column($data['account_statement'], 'amount'));
        $this->assertSame(['INV-000001'], array_column($data['sales'], 'reference'));
        $this->assertSame('شاحن 20W', $data['sales'][0]['items'][0]['name']);
        $this->assertArrayNotHasKey('cost_total', $data['sales'][0]);
        $this->assertArrayNotHasKey('unit_cost', $data['sales'][0]['items'][0]);
        $this->assertSame(['RP-00001'], array_column($data['repairs'], 'reference'));
        $this->assertSame('356789012345678', $data['repairs'][0]['imei']);
        $this->assertArrayNotHasKey('unlock_code', $data['repairs'][0]);
        $this->assertSame(1, AuditEntry::query()->where('action', 'customers.exported')->count());
    }

    public function test_retention_setting_and_command_erase_only_eligible_customers(): void
    {
        $this->getJson('/api/v1/customers/privacy-settings')->assertOk()->assertJsonPath('data.retention_years', null);
        $this->putJson('/api/v1/customers/privacy-settings', ['retention_years' => 11])->assertUnprocessable();
        $this->putJson('/api/v1/customers/privacy-settings', ['retention_years' => 0])->assertUnprocessable();

        // Three years ago: one quiet customer, one who still owes, one with a device still in the shop,
        // one who bought again recently, one who had a repair delivered recently.
        $this->travel(-3)->years();
        $quiet = $this->customer(['name' => 'ساكت', 'phone' => '01000000001']);
        $owes = $this->customer(['name' => 'عليه', 'phone' => '01000000002', 'opening_balance' => 1000]);
        $device = $this->receive(['customer_name' => 'جهازه', 'customer_phone' => '01000000003']);
        $buyer = $this->customer(['name' => 'رجع اشترى', 'phone' => '01000000004']);
        $repaired = $this->receive(['customer_name' => 'اتصلح', 'customer_phone' => '01000000005']);
        $this->travelBack();
        $this->sell([['method' => 'cash', 'amount' => 45000]], ['customer_id' => $buyer['id']])->assertCreated();
        $this->postJson("/api/v1/repairs/tickets/{$repaired['id']}/status", ['status' => 'rejected'])->assertOk();
        $this->postJson("/api/v1/repairs/tickets/{$repaired['id']}/deliver", [])->assertOk();
        $recent = $this->customer(['name' => 'جديد', 'phone' => '01000000006']);

        // Off by default: nothing happens.
        $this->artisan('customers:erase-inactive')->assertSuccessful();
        $this->assertNull($this->inShop(fn () => Customer::query()->whereNotNull('erased_at')->value('id')));

        $this->putJson('/api/v1/customers/privacy-settings', ['retention_years' => 2])->assertOk()->assertJsonPath('data.retention_years', 2);
        $this->assertSame(1, AuditEntry::query()->where('action', 'customers.retention_changed')->count());

        // Another shop without the setting keeps its old customers.
        $mine = $this->owner;
        $otherOwner = $this->registerShop();
        Sanctum::actingAs($otherOwner);
        $this->travel(-3)->years();
        $otherShops = $this->postJson('/api/v1/customers', ['name' => 'عند محل تاني', 'phone' => '01000000001'])->assertCreated()->json('data');
        $this->travelBack();
        Sanctum::actingAs($mine);

        $this->artisan('customers:erase-inactive')->assertSuccessful();
        $this->relay();

        $erased = $this->inShop(fn () => Customer::query()->whereNotNull('erased_at')->pluck('id')->all());
        $this->assertSame([$quiet['id']], $erased);
        foreach ([$owes, $buyer, $recent] as $kept) {
            $this->assertNotSame('عميل محذوف', $this->getJson("/api/v1/customers/{$kept['id']}")->json('data.name'));
        }
        $this->assertNull($this->inShop(fn () => Customer::query()->whereKey($device['customer_id'])->value('erased_at')));
        $this->assertNull($this->inShop(fn () => Customer::query()->whereKey($repaired['customer_id'])->value('erased_at')));
        $this->assertSame('عند محل تاني', Customer::withoutTenancy()->whereKey($otherShops['id'])->value('name'));
        $this->assertSame(1, AuditEntry::query()->where('action', 'customers.erased')->where('properties->reason', 'retention')->count());

        // Turned off again.
        $this->putJson('/api/v1/customers/privacy-settings', ['retention_years' => null])->assertOk()->assertJsonPath('data.retention_years', null);
    }
}
