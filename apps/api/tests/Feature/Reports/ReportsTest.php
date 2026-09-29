<?php

namespace Tests\Feature\Reports;

use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use OpenSpout\Reader\XLSX\Reader;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class ReportsTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private array $customer;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $shift = $this->openShift(10000)->json('data');

        // Two chargers (450, cost 300) and three cases (100, cost 40), 50 off the invoice, 200 of it on credit.
        $this->customer = $this->postJson('/api/v1/customers', ['name' => 'سامي', 'phone' => '01055555555'])->assertCreated()->json('data');
        $big = $this->postJson('/api/v1/sales', [
            'items' => [['variant_id' => $this->v[1], 'qty' => 2], ['variant_id' => $this->v[0], 'qty' => 3]],
            'discount' => 5000,
            'customer_id' => $this->customer['id'],
            'payments' => [['method' => 'cash', 'amount' => 100000], ['method' => 'credit', 'amount' => 20000]],
        ])->assertCreated()->json('data');
        // One case, by card.
        $this->postJson('/api/v1/sales', ['items' => [['variant_id' => $this->v[0], 'qty' => 1]], 'payments' => [['method' => 'card', 'amount' => 10000]]])->assertCreated();
        // One case of the first sale comes back damaged, refunded in cash.
        $case = collect($big['items'])->firstWhere('name', 'جراب');
        $this->postJson("/api/v1/sales/{$big['id']}/returns", ['refund_method' => 'cash', 'items' => [['sale_item_id' => $case['id'], 'qty' => 1, 'restock' => false]]])->assertCreated();

        $this->postJson('/api/v1/cash/movements', ['type' => 'expense', 'amount' => 3000, 'category' => 'transport'])->assertCreated();
        $this->postJson("/api/v1/cash/shifts/{$shift['id']}/close", ['counted' => ['cash' => 100000]])->assertOk();
    }

    private function report(string $key, array $query = []): array
    {
        return $this->getJson("/api/v1/reports/{$key}?".http_build_query($query))->assertOk()->json('data');
    }

    private function summary(array $report, string $label): mixed
    {
        return collect($report['summary'])->firstWhere('label', $label)['value'] ?? null;
    }

    public function test_the_catalog_lists_every_report_for_the_owner(): void
    {
        $index = $this->getJson('/api/v1/reports')->assertOk()->json('data');

        $this->assertSame(['sales', 'products', 'staff', 'payments', 'inventory', 'receivables', 'payables', 'expenses', 'shifts'], array_column($index['reports'], 'key'));
        $this->assertSame([$this->branchId], array_column($index['branches'], 'id'));
    }

    public function test_sales_and_profit_after_discount_returns_and_expenses(): void
    {
        $sales = $this->report('sales');

        // 2×450 + 3×100 = 1200 − 50 = 1150; the returned case refunds its share: 100 × 1150/1200 = 95.83.
        $refund = intdiv(10000 * 115000 + 60000, 120000);
        $net = 115000 - $refund + 10000;
        $this->assertSame($net, $this->summary($sales, 'صافي المبيعات'));
        $this->assertSame(2, $this->summary($sales, 'الفواتير'));
        // Cost: 2×300 + 3×40 (the damaged case stays a cost) + 40 = 760.
        $this->assertSame($net - 76000, $this->summary($sales, 'مجمل الربح'));
        $this->assertSame(3000, $this->summary($sales, 'المصروفات'));
        $this->assertSame($net - 76000 - 3000, $this->summary($sales, 'صافي الربح'));

        $today = collect($sales['rows'])->last();
        $this->assertSame([2, 125000, 5000, $refund, 20000], [$today['invoices'], $today['sales'], $today['discounts'], $today['returns'], $today['credit']]);
        $this->assertSame('daily', $sales['chart']['kind']);

        $monthly = $this->report('sales', ['options' => ['group' => 'month']]);
        $this->assertCount(1, $monthly['rows']);
        $this->assertSame('month', $monthly['columns'][0]['type']);
    }

    public function test_products_share_the_invoice_discount_and_keep_damaged_cost(): void
    {
        $rows = collect($this->report('products')['rows'])->keyBy('name');

        // Chargers: 900 × 1150/1200 = 862.50, cost 600.
        $this->assertSame([2, 86250, 60000], [$rows['شاحن 20W']['qty'], $rows['شاحن 20W']['revenue'], $rows['شاحن 20W']['cost']]);
        // Cases: 2 kept of the discounted 3 (300 × 1150/1200 × 2/3 = 191.67) + 100 by card; cost 3×40 + 40.
        $this->assertSame([3, 1, 19167 + 10000, 16000], [$rows['جراب']['qty'], $rows['جراب']['returned'], $rows['جراب']['revenue'], $rows['جراب']['cost']]);

        $byCategory = $this->report('products', ['options' => ['group' => 'category']]);
        $this->assertEqualsCanonicalizing(['شواحن', 'جرابات'], array_column($byCategory['rows'], 'name'));
    }

    public function test_payments_staff_receivables_expenses_and_shifts(): void
    {
        $payments = collect($this->report('payments')['rows'])->keyBy('method');
        $refund = intdiv(10000 * 115000 + 60000, 120000);
        $this->assertSame([95000, $refund], [$payments['كاش']['received'], $payments['كاش']['refunded']], 'cash net of the 50 change');
        $this->assertSame([10000, 20000], [$payments['فيزا']['net'], $payments['آجل']['net']]);

        $staff = $this->report('staff')['rows'];
        $this->assertSame([['المالك', 2]], array_map(fn ($r) => [$r['name'], $r['invoices']], $staff));

        $receivables = $this->report('receivables');
        $this->assertSame([['سامي', '01055555555', 20000]], array_map(fn ($r) => [$r['name'], $r['phone'], $r['balance']], $receivables['rows']));

        $expenses = $this->report('expenses');
        $this->assertSame([['نقل ومواصلات', 1, 3000]], array_map(fn ($r) => [$r['category'], $r['count'], $r['amount']], $expenses['rows']));
        $this->assertCount(1, $this->report('expenses', ['options' => ['group' => 'list']])['rows']);

        // Drawer: 100 + 950 cash − 95.83 refund − 30 expense = 924.17; counted 1000 → +75.83 over.
        $shift = $this->report('shifts')['rows'][0];
        $this->assertSame([92417, 100000, 7583], [$shift['expected'], $shift['counted'], $shift['difference']]);

        $inventory = $this->report('inventory');
        $this->assertSame((50 - 4) * 4000 + (20 - 2) * 30000, $this->summary($inventory, 'القيمة بالتكلفة'), 'the damaged case is not back in stock');
        $this->assertSame([(50 - 4) + (20 - 2)], [$this->summary($inventory, 'القطع في المخزن')]);
    }

    public function test_periods_branches_and_bad_input(): void
    {
        $yesterday = now('Africa/Cairo')->subDay()->toDateString();
        $this->assertSame(0, $this->summary($this->report('sales', ['from' => $yesterday, 'to' => $yesterday]), 'الفواتير'));

        $this->getJson('/api/v1/reports/sales?from=2020-01-01&to=2026-01-01')->assertUnprocessable()->assertJsonPath('code', 'period_too_long');
        $this->getJson('/api/v1/reports/sales?from=2026-02-01&to=2026-01-01')->assertUnprocessable();
        $this->getJson('/api/v1/reports/sales?branch=01999999-0000-7000-8000-000000000000')->assertForbidden();
        $this->getJson('/api/v1/reports/nope')->assertNotFound();
        // An unknown option falls back to the report's default (day by day).
        $this->assertSame('date', $this->report('sales', ['branch' => $this->branchId, 'options' => ['group' => 'bogus']])['columns'][0]['type']);
    }

    public function test_what_each_role_may_see(): void
    {
        Sanctum::actingAs($this->staff('cashier'));
        $this->getJson('/api/v1/reports')->assertForbidden();
        $this->getJson('/api/v1/reports/sales')->assertForbidden();

        Sanctum::actingAs($this->owner);
        $roleId = $this->postJson('/api/v1/roles', ['name' => 'محاسب', 'permissions' => ['reports.view']])->assertCreated()->json('data.id');
        $userId = $this->postJson('/api/v1/users', ['name' => 'محاسب', 'phone' => '01233334444', 'password' => 'password', 'role_id' => $roleId, 'branch_ids' => [$this->branchId]])
            ->assertCreated()->json('data.id');
        Sanctum::actingAs(User::query()->findOrFail($userId));

        $this->assertSame(['sales', 'products', 'staff', 'payments'], array_column($this->getJson('/api/v1/reports')->json('data.reports'), 'key'));
        $sales = $this->report('sales');
        $this->assertNotContains('profit', array_column($sales['columns'], 'key'));
        $this->assertNull($this->summary($sales, 'مجمل الربح'));
        $this->getJson('/api/v1/reports/shifts')->assertForbidden();
        $this->getJson('/api/v1/reports/inventory')->assertForbidden();
    }

    public function test_excel_export(): void
    {
        $response = $this->get('/api/v1/reports/products/export')->assertOk();
        $this->assertStringContainsString('spreadsheetml', (string) $response->headers->get('content-type'));

        $path = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($path, $response->streamedContent());
        $reader = new Reader;
        $reader->open($path);
        $cells = [];
        foreach ($reader->getSheetIterator() as $sheet) {
            foreach ($sheet->getRowIterator() as $row) {
                $cells[] = $row->toArray();
            }
        }
        $reader->close();

        $this->assertStringContainsString('مبيعات وأرباح الأصناف', (string) $cells[0][0]);
        $charger = collect($cells)->first(fn ($r) => ($r[0] ?? null) === 'شاحن 20W');
        $this->assertEquals(862.5, $charger[5], 'money in pounds');
    }

    public function test_another_shop_sees_nothing_of_mine(): void
    {
        Sanctum::actingAs($this->registerShop(ShopType::Accessories));
        $this->assertSame(0, $this->summary($this->report('sales'), 'الفواتير'));
        $this->assertSame([], $this->report('receivables')['rows']);
    }
}
