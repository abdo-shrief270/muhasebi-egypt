<?php

namespace Tests\Feature\Installments;

use App\Modules\Identity\PermissionResolver;
use App\Modules\Installments\Models\InstallmentItem;
use App\Modules\Installments\Models\InstallmentPayment;
use App\Modules\Installments\Support\Schedule;
use App\Support\Audit\AuditEntry;
use App\Support\Events\EventRelay;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** Installment plans over a credit sale / a customer's account: schedule, markup, collecting, late, cancel. */
class InstallmentsTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private array $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->postJson('/api/v1/modules/installments/trial')->assertOk();
        app(PermissionResolver::class)->forget(); // memoised per process; a real request starts fresh
        $this->openShift(100000);
        $this->customer = $this->postJson('/api/v1/customers', ['name' => 'كريم', 'phone' => '01012345678'])->assertCreated()->json('data');
    }

    /** The charger (450 ج): 50 ج cash now, 400 ج on the account. */
    private function creditSale(): array
    {
        return $this->sell([['method' => 'cash', 'amount' => 5000], ['method' => 'credit', 'amount' => 40000]], ['customer_id' => $this->customer['id']])->assertCreated()->json('data');
    }

    private function plan(array $overrides = [])
    {
        return $this->postJson('/api/v1/installments', [
            'customer_id' => $this->customer['id'],
            'principal' => 40000,
            'markup' => 2000,
            'count' => 4,
            'first_due_on' => CarbonImmutable::today()->addMonth()->toDateString(),
            ...$overrides,
        ]);
    }

    private function balance(): int
    {
        return $this->getJson("/api/v1/customers/{$this->customer['id']}")->assertOk()->json('data.balance');
    }

    public function test_the_schedule_splits_into_whole_pounds_and_the_last_takes_the_rest(): void
    {
        $items = Schedule::make(100050, 3, CarbonImmutable::parse('2026-01-31'));
        $this->assertSame([33300, 33300, 33450], array_column($items, 'amount'));
        $this->assertSame(['2026-01-31', '2026-02-28', '2026-03-31'], array_column($items, 'due_on'));
        $this->assertSame(['2026-01-01', '2026-03-01'], array_column(Schedule::make(1000, 2, CarbonImmutable::parse('2026-01-01'), 2), 'due_on'));
        $this->assertSame(3600, Schedule::markup(40000, 300, 3), '3% a month over 3 months on 400 ج = 36 ج');
    }

    public function test_a_credit_sale_becomes_a_plan_with_its_markup_on_the_account(): void
    {
        $sale = $this->creditSale();
        $this->assertSame(40000, $this->balance());

        $available = $this->getJson("/api/v1/installments/available?customer_id={$this->customer['id']}&sale_id={$sale['id']}")->assertOk()->json('data');
        $this->assertSame([40000, 0, 40000], [$available['available'], $available['in_plans'], $available['sale']['available']]);

        $plan = $this->plan(['sale_id' => $sale['id'], 'guarantor_name' => 'سامح', 'guarantor_phone' => '01112345678'])->assertCreated()->json('data');
        $this->assertSame(['INS-00001', 42000, 42000, 4, 'active', $sale['reference'], '+201112345678'], [$plan['reference'], $plan['total'], $plan['remaining'], $plan['count'], $plan['status'], $plan['sale_reference'], $plan['guarantor_phone']]);
        $this->assertSame([10500, 10500, 10500, 10500], array_column($plan['items'], 'amount'));
        $this->assertSame(42000, $this->balance(), 'the markup went on the account');
        $statement = $this->getJson("/api/v1/customers/{$this->customer['id']}/statement")->assertOk()->json('data');
        $this->assertContains('installment_markup', array_column($statement, 'type'));
        $this->assertTrue(AuditEntry::query()->where('action', 'installments.created')->exists());

        // Nothing left to plan on that sale or that account.
        $this->plan(['sale_id' => $sale['id'], 'principal' => 100])->assertUnprocessable()->assertJsonPath('code', 'principal_over_sale');
        $this->plan(['principal' => 100])->assertUnprocessable()->assertJsonPath('code', 'principal_over_balance');
    }

    public function test_collecting_pays_installments_in_order_through_the_drawer_and_completes_the_plan(): void
    {
        $this->creditSale();
        $plan = $this->plan()->assertCreated()->json('data');
        $cashBefore = $this->getJson('/api/v1/cash/current')->json('data.expected.cash');

        $after = $this->postJson("/api/v1/installments/{$plan['id']}/payments", ['amount' => 15000, 'method' => 'cash'])->assertOk()->json('data');
        $this->assertSame([15000, 27000], [$after['paid'], $after['remaining']]);
        $this->assertSame([[10500, 0], [4500, 6000]], array_map(fn ($i) => [$i['paid'], $i['remaining']], array_slice($after['items'], 0, 2)));
        $this->assertSame($cashBefore + 15000, $this->getJson('/api/v1/cash/current')->json('data.expected.cash'));
        $this->assertSame(27000, $this->balance());

        $this->postJson("/api/v1/installments/{$plan['id']}/payments", ['amount' => 27001, 'method' => 'cash'])->assertUnprocessable()->assertJsonPath('code', 'amount_over_remaining');
        $done = $this->postJson("/api/v1/installments/{$plan['id']}/payments", ['amount' => 27000, 'method' => 'wallet'])->assertOk()->json('data');
        $this->assertSame(['completed', 0, 4], [$done['status'], $done['remaining'], $done['paid_count']]);
        $this->assertSame(0, $this->balance());

        // Collecting through the plan isn't counted again by the account listener.
        app(EventRelay::class)->publishPending();
        $this->assertSame(2, InstallmentPayment::query()->count());
        $this->expectException(LogicException::class);
        InstallmentPayment::query()->first()->delete();
    }

    public function test_a_payment_from_the_customer_page_reaches_the_plan_once_other_debt_is_paid(): void
    {
        $this->creditSale();
        $plan = $this->plan(['principal' => 30000, 'markup' => 0, 'count' => 3])->assertCreated()->json('data');
        // 400 ج owed: 300 in the plan, 100 ordinary آجل. 150 paid on the account: 100 clears the آجل, 50 reaches the plan.
        $this->postJson("/api/v1/customers/{$this->customer['id']}/payments", ['amount' => 15000, 'payment_method' => 'cash'])->assertCreated();
        app(EventRelay::class)->publishPending();

        $seen = $this->getJson("/api/v1/installments/{$plan['id']}")->assertOk()->json('data');
        $this->assertSame([5000, 25000], [$seen['paid'], $seen['remaining']]);
        $this->assertSame('account', $seen['payments'][0]['source']);
    }

    public function test_late_installments_show_on_the_due_list_and_in_the_report(): void
    {
        $this->creditSale();
        $plan = $this->plan(['first_due_on' => CarbonImmutable::today()->toDateString()])->assertCreated()->json('data');
        // As if it was made 40 days ago (travelling would end the module's trial).
        $this->inShop(fn () => InstallmentItem::query()->where('plan_id', $plan['id'])->get()
            ->each(fn (InstallmentItem $i) => $i->update(['due_on' => $i->due_on->subDays(40)])));

        $due = $this->getJson('/api/v1/installments/due?when=late')->assertOk()->json();
        $this->assertCount(2, $due['data'], 'today+40: the first two are late');
        $this->assertSame([40, $plan['reference']], [$due['data'][0]['days_late'], $due['data'][0]['plan_reference']]);
        $this->assertSame([21000, 1], [$due['meta']['summary']['late_amount'], $due['meta']['summary']['late_plans']]);
        $this->assertSame([$plan['id']], array_column($this->getJson('/api/v1/installments?status=late')->assertOk()->json('data'), 'id'));

        $report = $this->getJson('/api/v1/reports/installments?options[view]=late&from='.CarbonImmutable::today()->subDays(60)->toDateString().'&to='.CarbonImmutable::today()->toDateString())->assertOk()->json('data');
        $this->assertSame([2, 21000, 40], [$report['rows'][0]['late_count'], $report['rows'][0]['late_amount'], $report['rows'][0]['days']]);
    }

    public function test_cancelling_leaves_the_rest_as_ordinary_credit(): void
    {
        $this->creditSale();
        $plan = $this->plan()->assertCreated()->json('data');
        $cancelled = $this->postJson("/api/v1/installments/{$plan['id']}/cancel", ['reason' => 'رجّع الجهاز'])->assertOk()->json('data');
        $this->assertSame('cancelled', $cancelled['status']);
        $this->assertSame(42000, $this->balance());
        $this->postJson("/api/v1/installments/{$plan['id']}/payments", ['amount' => 100, 'method' => 'cash'])->assertUnprocessable()->assertJsonPath('code', 'plan_not_active');
        // The debt can be planned again.
        $this->plan(['principal' => 42000, 'markup' => 0])->assertCreated();
    }

    public function test_switches_and_permissions(): void
    {
        $this->creditSale();
        $this->putJson('/api/v1/features/installments.guarantor_required', ['enabled' => true])->assertOk();
        $this->plan()->assertUnprocessable()->assertJsonPath('code', 'guarantor_required');
        $plan = $this->plan(['guarantor_name' => 'سامح', 'guarantor_phone' => '01112345678'])->assertCreated()->json('data');

        $this->putJson('/api/v1/features/installments.reminders', ['enabled' => false])->assertOk();
        $this->postJson('/api/v1/messages/log', ['template' => 'installment_reminder', 'phone' => '01012345678'])->assertForbidden();

        // A cashier collects but doesn't make or cancel plans.
        Sanctum::actingAs($this->staff('cashier'));
        $this->openShift();
        $this->getJson('/api/v1/installments')->assertOk();
        $this->postJson("/api/v1/installments/{$plan['id']}/payments", ['amount' => 1000, 'method' => 'cash'])->assertOk();
        $this->plan(['principal' => 100])->assertForbidden();
        $this->postJson("/api/v1/installments/{$plan['id']}/cancel")->assertForbidden();
    }
}
