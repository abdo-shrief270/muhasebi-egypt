<?php

namespace Tests\Feature\Modules;

use App\Modules\SupplierReturns\Models\BinItem;
use App\Support\Audit\AuditEntry;
use App\Support\Events\EventRelay;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

/**
 * The owner's switches that shape how the shop works: each one off makes the API refuse what it
 * covers (403 `feature_disabled` or a domain error), on it works, and the defaults keep today's
 * behaviour. The case costs 40 ج and sells for 100; the charger costs 300 and sells for 450.
 */
class ShopSwitchesTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    /** Every switch added with this page, and its default (on = removes something when turned off). */
    private const DEFAULTS = [
        'sales.discounts' => true,
        'sales.line_discounts' => true,
        'sales.price_levels' => true,
        'sales.hold_carts' => true,
        'sales.require_customer' => false,
        'sales.below_cost' => false,
        'sales.block_out_of_stock' => false,
        'sales.auto_print' => false,
        'sales.receipt_link' => true,
        'sales.returns' => true,
        'sales.return_window' => false,
        'customers.credit_sales' => true,
        'customers.debt_reminders' => true,
        'catalog.excel_import' => true,
        'catalog.bulk_prices' => true,
        'catalog.labels' => true,
        'inventory.price_check' => true,
        'cash.expenses' => true,
        'cash.deposits' => true,
        'cash.blind_close' => false,
        'repairs.public_tracking' => true,
        'repairs.status_whatsapp' => true,
        'repairs.deposits' => true,
        'repairs.warranty' => true,
        'repairs.commission' => true,
        'repairs.outsourcing' => true,
        'services.airtime' => true,
        'services.require_customer_phone' => false,
        'used_devices.device_photos_required' => false,
        'supplier_returns.auto_collect' => true,
        'reports.excel_export' => true,
        'reports.home_profit' => true,
    ];

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->openShift(100000);
    }

    private function switch(string $key, bool $on, array $extra = []): void
    {
        $this->putJson("/api/v1/features/{$key}", ['enabled' => $on, ...$extra])->assertOk();
    }

    private function refused($response, string $feature): void
    {
        $response->assertForbidden()->assertJsonPath('code', 'feature_disabled')->assertJsonPath('feature', $feature);
    }

    /** A sale of one case (100 ج) with whatever else is given. */
    private function sellCase(array $overrides = [])
    {
        return $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $this->v[0], 'qty' => 1, ...($overrides['item'] ?? [])]],
            'payments' => [['method' => 'cash', 'amount' => 10000]],
            ...array_diff_key($overrides, ['item' => true]),
        ]);
    }

    private function ticket(array $overrides = []): array
    {
        return $this->postJson('/api/v1/repairs/tickets', [
            'customer_name' => 'نادر', 'customer_phone' => '01234567890', 'device_name' => 'iPhone 11', 'reported_note' => 'شاشة', ...$overrides,
        ])->assertCreated()->json('data');
    }

    /** A ticket repaired for 200 ج labor and ready to hand back. */
    private function readyTicket(): string
    {
        $id = $this->ticket()['id'];
        $url = "/api/v1/repairs/tickets/{$id}";
        $this->postJson("{$url}/status", ['status' => 'repairing'])->assertOk();
        $this->patchJson($url, ['labor' => 20000])->assertOk();
        $this->postJson("{$url}/status", ['status' => 'ready'])->assertOk();

        return $id;
    }

    public function test_the_defaults_keep_todays_behaviour_and_nothing_is_customized(): void
    {
        $features = $this->getJson('/api/v1/auth/me')->assertOk()->json('data.features');
        foreach (self::DEFAULTS as $key => $default) {
            $this->assertArrayHasKey($key, $features, $key);
            $this->assertSame($default, $features[$key], $key);
        }
        $this->assertSame(['sales.below_cost' => 'warn', 'sales.return_window' => 14], array_intersect_key(
            $this->getJson('/api/v1/auth/me')->json('data.feature_settings'),
            ['sales.below_cost' => 1, 'sales.return_window' => 1],
        ));

        $groups = collect($this->getJson('/api/v1/features')->assertOk()->json('data'));
        $this->assertGreaterThanOrEqual(10, $groups->count());
        $all = $groups->flatMap(fn ($g) => $g['features']);
        $this->assertTrue($all->every(fn ($f) => $f['customized'] === false));
        $this->assertTrue($groups->every(fn ($g) => is_string($g['intro']) && $g['intro'] !== ''));
        $window = $all->firstWhere('key', 'sales.return_window');
        $this->assertSame(['int', 14, 1, 365, 'يوم'], [$window['setting']['type'], $window['setting']['default'], $window['setting']['min'], $window['setting']['max'], $window['setting']['unit']]);
        $this->assertSame(['warn', 'block'], array_column($all->firstWhere('key', 'sales.below_cost')['setting']['choices'], 'value'));
    }

    public function test_settings_are_validated_shown_as_customized_and_reset(): void
    {
        $this->putJson('/api/v1/features/sales.return_window', ['enabled' => true, 'value' => 0])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->putJson('/api/v1/features/sales.return_window', ['value' => 400])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->putJson('/api/v1/features/sales.return_window', ['value' => 'abc'])->assertUnprocessable();
        $this->putJson('/api/v1/features/sales.below_cost', ['value' => 'maybe'])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->putJson('/api/v1/features/sales.returns', ['enabled' => false, 'value' => 3])->assertUnprocessable()->assertJsonValidationErrors('value');
        $this->putJson('/api/v1/features/sales.returns', [])->assertUnprocessable();

        // Only the value: kept with the switch's current state (off), shown as differing from the default.
        $groups = $this->putJson('/api/v1/features/sales.return_window', ['value' => 30])->assertOk()->json('data');
        $window = collect($groups)->flatMap(fn ($g) => $g['features'])->firstWhere('key', 'sales.return_window');
        $this->assertSame([false, 30, true], [$window['enabled'], $window['value'], $window['customized']]);
        $this->switch('sales.return_window', true);
        $this->assertSame(30, $this->getJson('/api/v1/auth/me')->json('data.feature_settings')['sales.return_window']);
        $this->assertTrue(AuditEntry::query()->where('action', 'features.setting')->exists());

        // Back to the default by hand: nothing is stored.
        $this->putJson('/api/v1/features/sales.return_window', ['enabled' => false, 'value' => 14])->assertOk();
        $this->assertSame(0, DB::table('tenant_features')->where('feature_key', 'sales.return_window')->count());

        // «رجّع الافتراضي»: one switch, then a whole module.
        $this->switch('sales.returns', false);
        $this->switch('sales.hold_carts', false);
        $this->switch('cash.expenses', false);
        $this->deleteJson('/api/v1/features/sales.returns')->assertOk();
        $this->assertTrue($this->getJson('/api/v1/auth/me')->json('data.features')['sales.returns']);
        $this->deleteJson('/api/v1/features/modules/sales')->assertOk();
        $features = $this->getJson('/api/v1/auth/me')->json('data.features');
        $this->assertSame([true, false], [$features['sales.hold_carts'], $features['cash.expenses']], 'another module keeps its choice');
        $this->assertTrue(AuditEntry::query()->where('action', 'features.reset')->exists());
        $this->deleteJson('/api/v1/features/modules/nope')->assertNotFound();
        $this->deleteJson('/api/v1/features/nope.nope')->assertNotFound();

        Sanctum::actingAs($this->staff('manager'));
        $this->deleteJson('/api/v1/features/cash.expenses')->assertForbidden();
        $this->deleteJson('/api/v1/features/modules/cash')->assertForbidden();
    }

    public function test_discounts_and_price_levels_are_three_switches(): void
    {
        $this->sellCase(['item' => ['discount' => 1000], 'discount' => 500])->assertCreated();

        $this->switch('sales.line_discounts', false);
        $this->refused($this->sellCase(['item' => ['discount' => 1000]]), 'sales.line_discounts');
        $this->sellCase(['discount' => 500])->assertCreated();

        $this->switch('sales.discounts', false);
        $this->refused($this->sellCase(['discount' => 500]), 'sales.discounts');

        $this->sellCase(['price_level' => 'wholesale'])->assertCreated();
        $this->switch('sales.price_levels', false);
        $this->refused($this->sellCase(['price_level' => 'wholesale']), 'sales.price_levels');
        $this->sellCase()->assertCreated();
    }

    public function test_a_customer_on_every_sale(): void
    {
        $this->sellCase()->assertCreated();
        $this->switch('sales.require_customer', true);
        $this->sellCase()->assertUnprocessable()->assertJsonPath('code', 'customer_required');
        $this->sellCase(['customer_name' => '  '])->assertUnprocessable()->assertJsonPath('code', 'customer_required');
        $this->sellCase(['customer_name' => 'سامح'])->assertCreated();
    }

    public function test_selling_below_cost_is_warned_about_or_refused(): void
    {
        // 100 ج case with 65 off = 35 < 40 cost.
        $this->sellCase(['item' => ['discount' => 6500], 'payments' => [['method' => 'cash', 'amount' => 3500]]])->assertCreated();

        $this->switch('sales.below_cost', true);
        $this->sellCase(['item' => ['discount' => 6500], 'payments' => [['method' => 'cash', 'amount' => 3500]]])
            ->assertStatus(409)->assertJsonPath('code', 'below_cost_confirm')->assertJsonPath('lines.0.variant_id', $this->v[0]);
        $this->sellCase(['item' => ['discount' => 6500], 'payments' => [['method' => 'cash', 'amount' => 3500]], 'confirm_below_cost' => true])->assertCreated();
        // The invoice discount counts too: 100 − 61 = 39.
        $this->sellCase(['discount' => 6100, 'payments' => [['method' => 'cash', 'amount' => 3900]]])->assertStatus(409);
        $this->sellCase(['discount' => 5000, 'payments' => [['method' => 'cash', 'amount' => 5000]]])->assertCreated();

        $this->switch('sales.below_cost', true, ['value' => 'block']);
        $this->sellCase(['item' => ['discount' => 6500], 'payments' => [['method' => 'cash', 'amount' => 3500]], 'confirm_below_cost' => true])
            ->assertUnprocessable()->assertJsonPath('code', 'below_cost');

        // A sale made offline already happened: kept as it was.
        $this->sellCase(['item' => ['discount' => 6500], 'payments' => [['method' => 'cash', 'amount' => 3500]], 'offline' => true, 'sold_at' => now()->subMinutes(10)->toIso8601String()])->assertCreated();
    }

    public function test_returns_and_the_return_window(): void
    {
        $sale = $this->sellCase()->assertCreated()->json('data');
        $return = fn () => $this->postJson("/api/v1/sales/{$sale['id']}/returns", [
            'refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => true]],
        ]);

        $this->switch('sales.returns', false);
        $this->refused($return(), 'sales.returns');
        $this->switch('sales.returns', true);

        $this->switch('sales.return_window', true, ['value' => 3]);
        DB::table('sales')->where('id', $sale['id'])->update(['completed_at' => now()->subDays(4)]);
        $return()->assertUnprocessable()->assertJsonPath('code', 'return_window_passed')->assertJsonPath('days', 3);
        DB::table('sales')->where('id', $sale['id'])->update(['completed_at' => now()->subDays(2)]);
        $return()->assertCreated();
    }

    public function test_credit_sales_can_be_closed_for_the_pos_and_repairs(): void
    {
        $customer = $this->postJson('/api/v1/customers', ['name' => 'أحمد', 'phone' => '01012345678'])->assertCreated()->json('data');
        $this->switch('customers.credit_sales', false);
        $this->refused($this->sellCase(['customer_id' => $customer['id'], 'payments' => [['method' => 'credit', 'amount' => 10000]]]), 'customers.credit_sales');

        $id = $this->readyTicket();
        $this->refused($this->postJson("/api/v1/repairs/tickets/{$id}/deliver", ['payments' => [['method' => 'credit', 'amount' => 20000]]]), 'customers.credit_sales');

        $this->switch('customers.credit_sales', true);
        $this->sellCase(['customer_id' => $customer['id'], 'payments' => [['method' => 'credit', 'amount' => 10000]]])->assertCreated();
    }

    public function test_whatsapp_messages_follow_their_switches(): void
    {
        $log = fn (string $template) => $this->postJson('/api/v1/messages/log', ['template' => $template]);
        $log('debt_reminder')->assertCreated();
        $log('repair_ready')->assertCreated();

        $this->switch('customers.debt_reminders', false);
        $this->refused($log('debt_reminder'), 'customers.debt_reminders');
        $this->switch('repairs.status_whatsapp', false);
        $this->refused($log('repair_received'), 'repairs.status_whatsapp');
        $this->assertSame('customers.debt_reminders', collect($this->getJson('/api/v1/messages/templates')->json('data'))->firstWhere('key', 'debt_reminder')['feature']);

        // A ready device isn't counted as "not told yet" when the shop doesn't send status messages.
        $this->readyTicket();
        $this->assertSame(0, $this->getJson('/api/v1/repairs/summary')->assertOk()->json('data.unnotified'));
        $this->switch('repairs.status_whatsapp', true);
        $this->assertSame(1, $this->getJson('/api/v1/repairs/summary')->json('data.unnotified'));
    }

    public function test_labels_bulk_prices_and_the_price_check(): void
    {
        $this->getJson('/api/v1/products/labels')->assertOk();
        $this->getJson('/api/v1/inventory/price-check?q=CASE')->assertOk();
        $preview = ['variant_ids' => [$this->v[0]]];
        $this->assertContains('/products/prices', array_column($this->getJson('/api/v1/auth/me')->json('data.menu'), 'to'));

        $this->switch('catalog.labels', false);
        $this->refused($this->getJson('/api/v1/products/labels'), 'catalog.labels');
        $this->switch('catalog.bulk_prices', false);
        $this->refused($this->postJson('/api/v1/products/prices/preview', $preview), 'catalog.bulk_prices');
        $this->refused($this->postJson('/api/v1/products/prices', $preview), 'catalog.bulk_prices');
        $this->assertNotContains('/products/prices', array_column($this->getJson('/api/v1/auth/me')->json('data.menu'), 'to'));
        $this->switch('inventory.price_check', false);
        $this->refused($this->getJson('/api/v1/inventory/price-check?q=CASE'), 'inventory.price_check');
    }

    public function test_expenses_deposits_and_a_blind_close(): void
    {
        $this->postJson('/api/v1/cash/movements', ['type' => 'expense', 'amount' => 1000, 'category' => 'other'])->assertCreated();
        $this->switch('cash.expenses', false);
        $this->refused($this->postJson('/api/v1/cash/movements', ['type' => 'expense', 'amount' => 1000, 'category' => 'other']), 'cash.expenses');
        $this->postJson('/api/v1/cash/movements', ['type' => 'deposit', 'amount' => 1000, 'note' => 'فكة'])->assertCreated();
        $this->switch('cash.deposits', false);
        $this->refused($this->postJson('/api/v1/cash/movements', ['type' => 'withdrawal', 'amount' => 1000, 'note' => 'للخزنة']), 'cash.deposits');

        // A cashier closes blind; whoever manages the cash still sees what was expected.
        $this->switch('cash.blind_close', true);
        $cashier = $this->staff('cashier');
        Sanctum::actingAs($cashier);
        $shift = $this->postJson('/api/v1/cash/shifts', ['opening_cash' => 5000])->assertCreated()->json('data');
        $this->assertSame([null, true], [$shift['expected'], $shift['blind']]);
        $closed = $this->postJson("/api/v1/cash/shifts/{$shift['id']}/close", ['counted' => ['cash' => 4000]])->assertOk()->json('data');
        $this->assertSame([null, null, 4000], [$closed['expected'], $closed['cash_difference'], $closed['counted']['cash'] ?? $closed['counted']]);
        Sanctum::actingAs($this->owner);
        $seen = $this->getJson("/api/v1/cash/shifts/{$shift['id']}")->assertOk()->json('data');
        $this->assertSame([false, -1000], [$seen['blind'], $seen['cash_difference']]);
    }

    public function test_repair_deposits_warranty_commission_and_outsourcing(): void
    {
        $deposit = ['deposits' => [['method' => 'cash', 'amount' => 5000]]];
        $this->ticket($deposit);
        $this->switch('repairs.deposits', false);
        $this->refused($this->postJson('/api/v1/repairs/tickets', ['customer_name' => 'نادر', 'customer_phone' => '01234567890', 'device_name' => 'iPhone 11', 'reported_note' => 'شاشة', ...$deposit]), 'repairs.deposits');
        $this->ticket();

        $tech = $this->staff('technician');
        $this->putJson("/api/v1/repairs/commissions/{$tech->id}", ['type' => 'fixed', 'value' => 5000])->assertOk();
        $this->switch('repairs.warranty', false);
        $this->switch('repairs.commission', false);
        $this->refused($this->getJson('/api/v1/repairs/commissions'), 'repairs.commission');

        $id = $this->readyTicket();
        $this->patchJson("/api/v1/repairs/tickets/{$id}", ['technician_id' => $tech->id])->assertOk();
        $url = "/api/v1/repairs/tickets/{$id}";
        $this->refused($this->postJson("{$url}/deliver", ['payments' => [['method' => 'cash', 'amount' => 20000]], 'warranty_days' => 30]), 'repairs.warranty');
        $done = $this->postJson("{$url}/deliver", ['payments' => [['method' => 'cash', 'amount' => 20000]]])->assertOk()->json('data');
        $this->assertNull($done['commission']);
        $this->assertSame(0, (int) DB::table('repair_tickets')->where('id', $id)->value('commission'));
        $this->refused($this->postJson("{$url}/warranty"), 'repairs.warranty');

        $this->switch('repairs.outsourcing', false);
        $this->refused($this->postJson("/api/v1/repairs/tickets/{$this->ticket()['id']}/outsource", ['partner_tenant_id' => $this->owner->tenant_id]), 'repairs.outsourcing');

        // Back on: the commission is fixed again at delivery.
        $this->switch('repairs.commission', true);
        $id = $this->readyTicket();
        $this->patchJson("/api/v1/repairs/tickets/{$id}", ['technician_id' => $tech->id])->assertOk();
        $this->assertSame(5000, $this->postJson("/api/v1/repairs/tickets/{$id}/deliver", ['payments' => [['method' => 'cash', 'amount' => 20000]]])->assertOk()->json('data.commission'));
    }

    public function test_airtime_and_the_customer_phone_at_the_services_counter(): void
    {
        $wallet = $this->postJson('/api/v1/services/accounts', ['kind' => 'wallet', 'provider' => 'vodafone', 'name' => 'فودافون كاش', 'phone' => '01000000001', 'opening_balance' => 100000])->assertCreated()->json('data');
        $airtime = $this->postJson('/api/v1/services/accounts', ['kind' => 'airtime', 'provider' => 'vodafone', 'name' => 'رصيد'])->assertCreated()->json('data');
        $this->postJson("/api/v1/services/accounts/{$airtime['id']}/fund", ['amount' => 10000, 'paid' => 9700, 'source' => 'drawer'])->assertCreated();

        $this->switch('services.airtime', false);
        $this->refused($this->postJson('/api/v1/services/transactions', ['account_id' => $airtime['id'], 'type' => 'topup', 'amount' => 1000]), 'services.airtime');
        $this->refused($this->postJson('/api/v1/services/accounts', ['kind' => 'airtime', 'provider' => 'orange', 'name' => 'رصيد 2']), 'services.airtime');
        $this->assertSame([$wallet['id']], array_column($this->getJson('/api/v1/services/accounts')->assertOk()->json('data'), 'id'));
        $this->assertSame(['wallet'], array_column($this->getJson('/api/v1/services/options')->json('data.kinds'), 'value'));
        $this->switch('services.airtime', true);
        $this->postJson('/api/v1/services/transactions', ['account_id' => $airtime['id'], 'type' => 'topup', 'amount' => 1000])->assertCreated();

        $this->switch('services.require_customer_phone', true);
        $this->postJson('/api/v1/services/transactions', ['account_id' => $wallet['id'], 'type' => 'deposit', 'amount' => 10000])
            ->assertUnprocessable()->assertJsonPath('code', 'customer_phone_required');
        $this->postJson('/api/v1/services/transactions', ['account_id' => $wallet['id'], 'type' => 'deposit', 'amount' => 10000, 'customer_phone' => '01012345678'])->assertCreated();
    }

    public function test_damaged_units_go_to_the_returns_bin_only_with_the_switch(): void
    {
        $back = function (): void {
            $sale = $this->sellCase()->assertCreated()->json('data');
            $this->postJson("/api/v1/sales/{$sale['id']}/returns", [
                'refund_method' => 'cash', 'items' => [['sale_item_id' => $sale['items'][0]['id'], 'qty' => 1, 'restock' => false]],
            ])->assertCreated();
        };

        $this->switch('supplier_returns.auto_collect', false);
        $back();
        app(EventRelay::class)->publishPending();
        $this->assertSame(0, BinItem::withoutGlobalScopes()->count());

        $this->switch('supplier_returns.auto_collect', true);
        $back();
        app(EventRelay::class)->publishPending();
        $this->assertSame(1, BinItem::withoutGlobalScopes()->count());
    }

    public function test_profit_can_be_kept_off_the_home_page(): void
    {
        $this->sellCase()->assertCreated();
        $this->assertSame(6000, $this->getJson('/api/v1/sales/stats')->assertOk()->json('data.today.profit'));
        $this->switch('reports.home_profit', false);
        $stats = $this->getJson('/api/v1/sales/stats')->assertOk()->json('data');
        $this->assertSame([null, null, 10000], [$stats['today']['profit'], $stats['period']['profit'], $stats['today']['revenue']]);
        $this->getJson('/api/v1/reports/sales')->assertOk();
    }
}
