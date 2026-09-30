<?php

namespace Tests\Feature\Services;

use App\Modules\Cash\Models\CashMovement;
use App\Modules\Services\Models\ServiceAccount;
use App\Modules\Services\Models\ServiceTransaction;
use App\Modules\Services\Support\FeeRule;
use App\Support\Audit\AuditEntry;
use App\Support\Events\EventRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use LogicException;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/** Wallet transfers and airtime top-ups: balances, the drawer, fees, profit, reversals, permissions. */
class ServicesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private string $shiftId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->shiftId = $this->openShift(100000)->json('data.id');
    }

    /** Vodafone Cash with 5,000 ج: 1% on deposits (min 5, rounded up to 1 ج), a flat 10 on withdrawals. */
    private function wallet(array $overrides = []): array
    {
        return $this->postJson('/api/v1/services/accounts', [
            'kind' => 'wallet', 'provider' => 'vodafone', 'name' => 'فودافون كاش 1', 'phone' => '01000000001',
            'opening_balance' => 500000,
            'fees' => [
                'deposit' => ['percent' => 100, 'min' => 500, 'round_to' => 100],
                'withdraw' => ['fixed' => 1000],
            ],
            ...$overrides,
        ])->assertCreated()->json('data');
    }

    /** Vodafone airtime: 1,000 ج of balance bought for 970 ج; 1 ج per top-up. */
    private function airtime(): array
    {
        $account = $this->postJson('/api/v1/services/accounts', [
            'kind' => 'airtime', 'provider' => 'vodafone', 'name' => 'رصيد فودافون',
            'fees' => ['topup' => ['fixed' => 100]],
        ])->assertCreated()->json('data');
        $this->postJson("/api/v1/services/accounts/{$account['id']}/fund", ['amount' => 100000, 'paid' => 97000, 'source' => 'drawer'])->assertCreated();

        return $account;
    }

    private function operate(string $accountId, string $type, int $amount, array $extra = [])
    {
        return $this->postJson('/api/v1/services/transactions', ['account_id' => $accountId, 'type' => $type, 'amount' => $amount, ...$extra]);
    }

    private function balance(string $accountId): int
    {
        return $this->getJson("/api/v1/services/accounts/{$accountId}")->assertOk()->json('data.balance');
    }

    private function expectedCash(): int
    {
        return $this->getJson('/api/v1/cash/current')->assertOk()->json('data.expected.cash');
    }

    public function test_deposit_sends_from_the_wallet_and_takes_amount_plus_fee_in_cash(): void
    {
        $wallet = $this->wallet();
        $this->assertSame(500000, $wallet['balance']);
        $this->assertSame(['deposit', 'withdraw'], $wallet['operations']);

        // 1,000 ج → 1% = 10 ج.
        $t = $this->operate($wallet['id'], 'deposit', 100000, ['customer_phone' => '01012345678', 'customer_name' => 'أحمد'])->assertCreated()->json('data');
        $this->assertSame([100000, 1000, 1000, 101000, -100000, 400000], [$t['amount'], $t['fee'], $t['profit'], $t['cash'], $t['balance_change'], $t['balance_after']]);
        $this->assertSame('+201012345678', $t['customer_phone']);
        $this->assertSame(400000, $this->balance($wallet['id']));
        $this->assertSame(100000 + 101000, $this->expectedCash());

        // 120 ج → 1.2 ج, but the minimum is 5.
        $this->assertSame(500, $this->operate($wallet['id'], 'deposit', 12000)->assertCreated()->json('data.fee'));
        // 1,234.50 ج → 12.345 → 12.35, rounded up to 13.
        $this->assertSame(1300, $this->operate($wallet['id'], 'deposit', 123450)->assertCreated()->json('data.fee'));

        // Can't send more than the wallet holds; nothing is saved.
        $this->operate($wallet['id'], 'deposit', 1000000)->assertUnprocessable()->assertJsonPath('code', 'insufficient_balance');
        $this->assertSame(500000 - 100000 - 12000 - 123450, $this->balance($wallet['id']));
        $this->assertSame(1, AuditEntry::query()->where('action', 'services.deposit')->where('description', 'like', '%1,000 ج%')->count());
    }

    public function test_withdraw_takes_the_fee_off_the_cash_or_on_top_in_the_wallet(): void
    {
        $wallet = $this->wallet();

        // «cash» mode: the customer sends 500, takes 490.
        $t = $this->operate($wallet['id'], 'withdraw', 50000)->assertCreated()->json('data');
        $this->assertSame([1000, 1000, -49000, 50000, 550000], [$t['fee'], $t['profit'], $t['cash'], $t['balance_change'], $t['balance_after']]);
        $this->assertSame(100000 - 49000, $this->expectedCash());

        // «wallet» mode: the customer sends 510, takes 500.
        $this->patchJson("/api/v1/services/accounts/{$wallet['id']}", ['withdraw_fee_mode' => 'wallet'])->assertOk();
        $t = $this->operate($wallet['id'], 'withdraw', 50000)->assertCreated()->json('data');
        $this->assertSame([1000, 1000, -50000, 51000, 601000, 'wallet'], [$t['fee'], $t['profit'], $t['cash'], $t['balance_change'], $t['balance_after'], $t['fee_mode']]);
        $this->assertSame(100000 - 49000 - 50000, $this->expectedCash());

        // Top-ups aren't done on a wallet.
        $this->operate($wallet['id'], 'topup', 1000)->assertUnprocessable()->assertJsonPath('code', 'operation_not_allowed');
    }

    public function test_airtime_is_bought_at_a_discount_and_the_margin_is_profit(): void
    {
        $line = $this->airtime();
        $this->assertSame(100000 - 97000, $this->expectedCash(), 'the distributor was paid from the drawer');
        $account = $this->getJson("/api/v1/services/accounts/{$line['id']}")->json('data');
        $this->assertSame([100000, 97000], [$account['balance'], $account['cost_value']]);

        // A 100 ج top-up for 101 ج: its cost is 97 → profit 4 (1 fee + 3 margin).
        $t = $this->operate($line['id'], 'topup', 10000)->assertCreated()->json('data');
        $this->assertSame([100, 10100, 400, 90000], [$t['fee'], $t['cash'], $t['profit'], $t['balance_after']]);
        $this->assertSame(3000 + 10100, $this->expectedCash());
        $this->assertSame(87300, $this->getJson("/api/v1/services/accounts/{$line['id']}")->json('data.cost_value'));

        // Using the rest costs the rest.
        $t = $this->operate($line['id'], 'topup', 90000)->assertCreated()->json('data');
        $this->assertSame(90000 + 100 - 87300, $t['profit']);
        $this->assertSame([0, 0], [$this->balance($line['id']), $this->getJson("/api/v1/services/accounts/{$line['id']}")->json('data.cost_value')]);
        $this->operate($line['id'], 'topup', 1000)->assertUnprocessable()->assertJsonPath('code', 'insufficient_balance');

        // Airtime can't be cashed out; a wallet is funded 1:1.
        $this->postJson("/api/v1/services/accounts/{$line['id']}/cash-out", ['amount' => 100, 'source' => 'drawer'])->assertUnprocessable();
        $wallet = $this->wallet(['opening_balance' => 0]);
        $this->postJson("/api/v1/services/accounts/{$wallet['id']}/fund", ['amount' => 1000, 'paid' => 900, 'source' => 'safe'])->assertUnprocessable()->assertJsonPath('code', 'wallet_fund_at_par');
    }

    public function test_funding_and_cashing_out_through_the_drawer_or_the_safe(): void
    {
        $wallet = $this->wallet(['opening_balance' => 0]);
        $this->postJson("/api/v1/services/accounts/{$wallet['id']}/fund", ['amount' => 200000, 'source' => 'safe'])->assertCreated();
        $this->assertSame(100000, $this->expectedCash(), 'the safe is not the drawer');
        $this->postJson("/api/v1/services/accounts/{$wallet['id']}/fund", ['amount' => 50000, 'source' => 'drawer'])->assertCreated()->assertJsonPath('data.cash', -50000);
        $this->postJson("/api/v1/services/accounts/{$wallet['id']}/cash-out", ['amount' => 30000, 'source' => 'drawer'])->assertCreated()->assertJsonPath('data.profit', 0);
        $this->assertSame(100000 - 50000 + 30000, $this->expectedCash());
        $this->assertSame(220000, $this->balance($wallet['id']));
        $this->postJson("/api/v1/services/accounts/{$wallet['id']}/cash-out", ['amount' => 300000, 'source' => 'safe'])->assertUnprocessable()->assertJsonPath('code', 'insufficient_balance');
    }

    public function test_every_operation_needs_an_open_shift_for_its_cash(): void
    {
        $wallet = $this->wallet();
        $this->postJson("/api/v1/cash/shifts/{$this->shiftId}/close", ['counted' => ['cash' => 100000]])->assertOk();

        $this->operate($wallet['id'], 'deposit', 10000)->assertStatus(409)->assertJsonPath('code', 'shift_not_open');
        $this->operate($wallet['id'], 'withdraw', 10000)->assertStatus(409);
        $this->postJson("/api/v1/services/accounts/{$wallet['id']}/fund", ['amount' => 1000, 'source' => 'drawer'])->assertStatus(409);
        // Nothing moved.
        $this->assertSame(500000, $this->balance($wallet['id']));
        $this->assertSame(1, ServiceTransaction::query()->count(), 'only the opening balance');
        // The safe needs no shift.
        $this->postJson("/api/v1/services/accounts/{$wallet['id']}/fund", ['amount' => 1000, 'source' => 'safe'])->assertCreated();
    }

    public function test_the_shift_close_counts_services_cash(): void
    {
        $wallet = $this->wallet();
        $this->operate($wallet['id'], 'deposit', 100000)->assertCreated(); // +1,010
        $this->operate($wallet['id'], 'withdraw', 20000)->assertCreated(); // -190

        $closed = $this->postJson("/api/v1/cash/shifts/{$this->shiftId}/close", ['counted' => ['cash' => 100000 + 101000 - 19000]])->assertOk()->json('data');
        $this->assertSame(100000 + 101000 - 19000, $closed['expected']['cash']);
        $this->assertSame(0, $closed['cash_difference']);
        $service = collect($this->getJson("/api/v1/cash/shifts/{$this->shiftId}")->json('data.by_type'))->firstWhere('type', 'service');
        $this->assertSame([82000, 2, 'شحن وتحويلات'], [$service['amount'], $service['count'], $service['label']]);
    }

    public function test_the_fee_is_suggested_and_changing_it_needs_permission(): void
    {
        $wallet = $this->wallet();
        // The owner may change it; the suggestion is kept on the row.
        $t = $this->operate($wallet['id'], 'deposit', 100000, ['fee' => 1500])->assertCreated()->json('data');
        $this->assertSame([1500, 1000, 1500], [$t['fee'], $t['suggested_fee'], $t['profit']]);
        $this->assertSame(1, AuditEntry::query()->where('action', 'services.deposit')->where('description', 'like', '%المقترحة 10 ج%')->count());

        $cashier = $this->staff('cashier');
        Sanctum::actingAs($cashier);
        $this->openShift();
        $this->operate($wallet['id'], 'deposit', 100000, ['fee' => 500])->assertForbidden()->assertJsonPath('code', 'fee_locked')->assertJsonPath('suggested_fee', 1000);
        $this->operate($wallet['id'], 'deposit', 100000, ['fee' => 1000])->assertCreated();
        $t = $this->operate($wallet['id'], 'deposit', 100000)->assertCreated()->json('data');
        $this->assertSame(1000, $t['fee']);
        $this->assertNull($t['profit'], 'a cashier does not see the profit');
    }

    public function test_fee_rule_math(): void
    {
        $this->assertSame(0, FeeRule::none()->feeFor(100000));
        $this->assertSame(1500, (new FeeRule(percent: 150))->feeFor(100000));
        $this->assertSame(2000, (new FeeRule(percent: 100, fixed: 500, roundTo: 1000))->feeFor(100000), '10 + 5 = 15 → 20');
        $this->assertSame(3000, (new FeeRule(percent: 100, max: 3000))->feeFor(1000000));
        $this->assertSame(500, (new FeeRule(percent: 100, min: 500))->feeFor(1000));
        $this->assertSame(0, (new FeeRule(fixed: 500))->feeFor(0));
    }

    public function test_a_reversal_puts_everything_back_once(): void
    {
        $line = $this->airtime();
        $wallet = $this->wallet();
        $deposit = $this->operate($wallet['id'], 'deposit', 100000)->assertCreated()->json('data');
        $topup = $this->operate($line['id'], 'topup', 10000)->assertCreated()->json('data');
        $cash = $this->expectedCash();

        $r = $this->postJson("/api/v1/services/transactions/{$deposit['id']}/reverse", ['reason' => 'رقم غلط'])->assertCreated()->json('data');
        $this->assertSame([-100000, -1000, -1000, -101000, 100000, 500000, $deposit['id']], [$r['amount'], $r['fee'], $r['profit'], $r['cash'], $r['balance_change'], $r['balance_after'], $r['reverses_id']]);
        $this->postJson("/api/v1/services/transactions/{$topup['id']}/reverse", ['reason' => 'الشحن ما وصلش'])->assertCreated();
        $this->assertSame($cash - 101000 - 10100, $this->expectedCash());
        $account = $this->getJson("/api/v1/services/accounts/{$line['id']}")->json('data');
        $this->assertSame([100000, 97000], [$account['balance'], $account['cost_value']]);

        // Once only; a reversal isn't reversed; the original shows it; the rows stay.
        $this->postJson("/api/v1/services/transactions/{$deposit['id']}/reverse", ['reason' => 'تاني'])->assertStatus(409)->assertJsonPath('code', 'already_reversed');
        $this->postJson("/api/v1/services/transactions/{$r['id']}/reverse", ['reason' => 'تاني'])->assertUnprocessable()->assertJsonPath('code', 'already_reversal');
        $this->postJson('/api/v1/services/transactions/'.ServiceTransaction::query()->where('type', 'opening')->value('id').'/reverse', ['reason' => 'x'])->assertUnprocessable();
        $this->assertTrue($this->getJson("/api/v1/services/transactions/{$deposit['id']}")->json('data.reversed'));
        $this->assertSame(1, AuditEntry::query()->where('action', 'services.reversed')->where('description', 'like', '%رقم غلط%')->count());

        $this->expectException(LogicException::class);
        ServiceTransaction::query()->findOrFail($deposit['id'])->update(['amount' => 1]);
    }

    public function test_permissions_by_role(): void
    {
        $wallet = $this->wallet();
        $own = $this->operate($wallet['id'], 'deposit', 10000)->assertCreated()->json('data');

        $cashier = $this->staff('cashier');
        Sanctum::actingAs($cashier);
        $this->openShift();
        $this->getJson('/api/v1/services/accounts')->assertOk()->assertJsonPath('data.0.cost_value', null);
        $mine = $this->operate($wallet['id'], 'withdraw', 10000)->assertCreated()->json('data');
        // Settings, funding and someone else's operation are not the cashier's.
        $this->postJson('/api/v1/services/accounts', ['kind' => 'wallet', 'provider' => 'orange', 'name' => 'أورنج'])->assertForbidden();
        $this->patchJson("/api/v1/services/accounts/{$wallet['id']}", ['name' => 'x'])->assertForbidden();
        $this->postJson("/api/v1/services/accounts/{$wallet['id']}/fund", ['amount' => 100, 'source' => 'safe'])->assertForbidden();
        $this->postJson("/api/v1/services/transactions/{$own['id']}/reverse", ['reason' => 'x'])->assertForbidden();
        // Their own slip, the same day, they can fix.
        $this->postJson("/api/v1/services/transactions/{$mine['id']}/reverse", ['reason' => 'غلط'])->assertCreated();

        // A technician has no services at all; a manager has all of it.
        Sanctum::actingAs($this->owner);
        $tech = $this->staff('technician');
        $manager = $this->staff('manager');
        Sanctum::actingAs($tech);
        $this->getJson('/api/v1/services/accounts')->assertForbidden();
        Sanctum::actingAs($manager);
        $this->openShift();
        $this->postJson("/api/v1/services/accounts/{$wallet['id']}/fund", ['amount' => 100, 'source' => 'safe'])->assertCreated();
        $this->postJson("/api/v1/services/transactions/{$own['id']}/reverse", ['reason' => 'x'])->assertCreated();
    }

    public function test_accounts_are_per_branch_and_warn_past_the_daily_limit(): void
    {
        $wallet = $this->wallet(['daily_limit' => 150000]);
        $this->postJson('/api/v1/services/accounts', ['kind' => 'wallet', 'provider' => 'vodafone', 'name' => 'فودافون كاش 1'])->assertUnprocessable();
        $this->postJson('/api/v1/services/accounts', ['kind' => 'airtime', 'provider' => 'instapay', 'name' => 'x'])->assertUnprocessable();

        $this->assertNull($this->operate($wallet['id'], 'deposit', 100000)->assertCreated()->json('meta.warning'));
        $this->assertStringContainsString('الحد اليومي', $this->operate($wallet['id'], 'withdraw', 60000)->assertCreated()->json('meta.warning'));
        $this->assertSame(160000, $this->getJson('/api/v1/services/accounts')->json('data.0.today_used'));
        $this->assertSame(['operations' => 2, 'fees' => 2000], $this->getJson('/api/v1/services/accounts')->json('meta.today'));

        // Another branch doesn't see or use it.
        $this->postJson('/api/v1/modules/multi_branch/trial')->assertOk();
        $other = $this->postJson('/api/v1/branches', ['name' => 'فرع 2'])->assertCreated()->json('data.id');
        $this->withHeader('X-Branch-Id', $other);
        $this->assertSame([], $this->getJson('/api/v1/services/accounts')->json('data'));
        $this->operate($wallet['id'], 'deposit', 1000)->assertUnprocessable()->assertJsonPath('code', 'account_other_branch');
    }

    public function test_history_filters(): void
    {
        $wallet = $this->wallet();
        $line = $this->airtime();
        $this->operate($wallet['id'], 'deposit', 10000, ['customer_phone' => '01012345678'])->assertCreated();
        $this->operate($wallet['id'], 'withdraw', 20000, ['customer_name' => 'منى'])->assertCreated();
        $this->operate($line['id'], 'topup', 1000)->assertCreated();

        $list = fn (array $q) => array_column($this->getJson('/api/v1/services/transactions?'.http_build_query($q))->assertOk()->json('data'), 'type');
        $this->assertSame(['topup', 'withdraw', 'deposit', 'fund', 'opening'], $list([]));
        $this->assertSame(['withdraw', 'deposit', 'opening'], $list(['account_id' => $wallet['id']]));
        $this->assertSame(['topup'], $list(['type' => 'topup']));
        $this->assertSame(['deposit'], $list(['q' => '01012345678']));
        $this->assertSame(['withdraw'], $list(['q' => 'منى']));
        $this->assertSame([], $list(['to' => '2020-01-01']));
    }

    public function test_the_report_and_the_dashboard_keep_services_apart_from_goods(): void
    {
        $wallet = $this->wallet();
        $line = $this->airtime();
        $this->operate($wallet['id'], 'deposit', 100000)->assertCreated(); // fee 10
        $w = $this->operate($wallet['id'], 'withdraw', 20000)->assertCreated()->json('data'); // fee 10
        $this->operate($line['id'], 'topup', 10000)->assertCreated(); // fee 1 + margin 3
        $this->postJson("/api/v1/services/transactions/{$w['id']}/reverse", ['reason' => 'x'])->assertCreated();
        $this->sell([['method' => 'cash', 'amount' => 45000]])->assertCreated();

        $report = $this->getJson('/api/v1/reports/services?options[group]=account')->assertOk()->json('data');
        $summary = collect($report['summary'])->pluck('value', 'label');
        $this->assertSame([2, 110000, 1100, 1400], [$summary['العمليات'], $summary['المبالغ اللي عدّت'], $summary['العمولات'], $summary['مكسب الخدمات']]);
        $rows = collect($report['rows'])->keyBy('name');
        $this->assertSame([1, 1000, 0, 1000], [$rows['فودافون كاش 1']['operations'], $rows['فودافون كاش 1']['fees'], $rows['فودافون كاش 1']['margin'], $rows['فودافون كاش 1']['profit']]);
        $this->assertSame([100, 300, 400], [$rows['رصيد فودافون']['fees'], $rows['رصيد فودافون']['margin'], $rows['رصيد فودافون']['profit']]);

        $byType = collect($this->getJson('/api/v1/reports/services?options[group]=type')->json('data.rows'))->keyBy('name');
        $this->assertSame([0, 0, 0], [$byType['سحب']['operations'], $byType['سحب']['volume'], $byType['سحب']['profit']]);
        $this->assertCount(1, $this->getJson('/api/v1/reports/services?options[group]=day')->json('data.rows'));

        // Goods profit stays goods only (charger 450 - 300); services come separately.
        $stats = $this->getJson('/api/v1/sales/stats')->assertOk()->json('data');
        $this->assertSame(15000, $stats['today']['profit']);
        $this->assertSame(['today' => 1400, 'today_operations' => 2, 'period' => 1400], $stats['services']);
    }

    public function test_erasing_a_customer_anonymises_their_operations(): void
    {
        $wallet = $this->wallet();
        $customer = $this->postJson('/api/v1/customers', ['name' => 'أحمد علي', 'phone' => '01012345678', 'consent' => true])->assertCreated()->json('data');
        $mine = $this->operate($wallet['id'], 'deposit', 10000, ['customer_phone' => '+201012345678', 'customer_name' => 'أحمد', 'reference' => 'TX1'])->assertCreated()->json('data');
        $other = $this->operate($wallet['id'], 'deposit', 10000, ['customer_phone' => '01198765432', 'customer_name' => 'منى'])->assertCreated()->json('data');

        $this->postJson("/api/v1/customers/{$customer['id']}/erase")->assertOk();
        app(EventRelay::class)->publishPending();

        $rows = $this->inShop(fn () => ServiceTransaction::query()->get()->keyBy('id'));
        $this->assertSame(['عميل محذوف', null, 'TX1', 10000], [$rows[$mine['id']]->customer_name, $rows[$mine['id']]->customer_phone, $rows[$mine['id']]->reference, $rows[$mine['id']]->amount]);
        $this->assertSame(['منى', '+201198765432'], [$rows[$other['id']]->customer_name, $rows[$other['id']]->customer_phone]);
        $this->assertSame(0, $this->inShop(fn () => CashMovement::query()->where('note', 'like', '%أحمد%')->count()), 'drawer notes never hold the name');
    }

    public function test_other_shops_cannot_see_or_use_the_accounts(): void
    {
        $wallet = $this->wallet();
        $this->assertSame(1, $this->inShop(fn () => ServiceAccount::query()->count()));

        $this->actingAsOwnerOf();
        $this->getJson('/api/v1/services/accounts')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson("/api/v1/services/accounts/{$wallet['id']}")->assertNotFound();
    }
}
