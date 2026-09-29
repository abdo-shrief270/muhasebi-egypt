<?php

namespace Tests\Feature\Repairs;

use App\Modules\Identity\Models\User;
use App\Modules\Repairs\Models\FaultType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class CommissionsAndReportTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    private User $tech;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->openShift();
        $this->tech = $this->staff('technician');
    }

    /** Received, repaired by the technician with the given labor (and one charger), delivered. */
    private function repair(int $labor, array $faults = ['كسر زجاج'], string $device = 'Samsung Galaxy A54', bool $part = false, int $discount = 0): array
    {
        $ids = $this->inShop(fn () => FaultType::query()->whereIn('name', $faults)->pluck('id')->all());
        $id = $this->postJson('/api/v1/repairs/tickets', [
            'customer_name' => 'عميل', 'customer_phone' => '01234567890', 'device_name' => $device, 'fault_ids' => $ids, 'technician_id' => $this->tech->id,
        ])->assertCreated()->json('data.id');
        $url = "/api/v1/repairs/tickets/{$id}";
        $this->postJson("{$url}/status", ['status' => 'repairing'])->assertOk();
        if ($part) {
            $this->postJson("{$url}/parts", ['variant_id' => $this->v[1], 'qty' => 1])->assertOk();
        }
        $this->patchJson($url, ['labor' => $labor, 'discount' => $discount])->assertOk();
        $this->postJson("{$url}/status", ['status' => 'ready'])->assertOk();
        $ticket = $this->getJson($url)->json('data');

        return $this->postJson("{$url}/deliver", ['payments' => $ticket['due'] > 0 ? [['method' => 'cash', 'amount' => $ticket['due']]] : [], 'warranty_days' => 30])->assertOk()->json('data');
    }

    public function test_commission_follows_the_technicians_rule(): void
    {
        $this->assertSame('none', collect($this->getJson('/api/v1/repairs/commissions')->assertOk()->json('data'))->firstWhere('user_id', $this->tech->id)['type']);
        $this->assertSame(0, $this->repair(40000)['commission'], 'no rule, no commission');

        // 15% of the labor after the discount: (400 - 100) × 15% = 45.
        $this->putJson("/api/v1/repairs/commissions/{$this->tech->id}", ['type' => 'percent', 'value' => 1500])->assertOk();
        $ticket = $this->repair(40000, discount: 10000);
        $this->assertSame([4500, '15% من المصنعية'], [$ticket['commission'], $ticket['commission_rule']]);

        // 20% of the profit: labor 300 + charger 450 (cost 300) → 450 profit → 90.
        $this->putJson("/api/v1/repairs/commissions/{$this->tech->id}", ['type' => 'percent', 'value' => 2000, 'base' => 'profit'])->assertOk();
        $this->assertSame(9000, $this->repair(30000, part: true)['commission']);

        // A fixed 50 per device; a warranty return earns nothing.
        $this->putJson("/api/v1/repairs/commissions/{$this->tech->id}", ['type' => 'fixed', 'value' => 5000])->assertOk();
        $first = $this->repair(20000);
        $this->assertSame(5000, $first['commission']);
        $back = $this->postJson("/api/v1/repairs/tickets/{$first['id']}/warranty")->assertCreated()->json('data');
        $this->postJson("/api/v1/repairs/tickets/{$back['id']}/status", ['status' => 'repairing'])->assertOk();
        $this->postJson("/api/v1/repairs/tickets/{$back['id']}/status", ['status' => 'ready'])->assertOk();
        $this->assertSame(0, $this->postJson("/api/v1/repairs/tickets/{$back['id']}/deliver", ['warranty_days' => 0])->assertOk()->json('data.commission'));

        $this->putJson("/api/v1/repairs/commissions/{$this->tech->id}", ['type' => 'percent', 'value' => 20000])->assertUnprocessable();
        $this->putJson('/api/v1/repairs/commissions/01999999-0000-7000-8000-000000000000', ['type' => 'fixed', 'value' => 1])->assertNotFound();

        // The technician sees their own commission; a cashier never does, nor can they set rules.
        Sanctum::actingAs($this->tech);
        $this->assertSame(5000, $this->getJson("/api/v1/repairs/tickets/{$first['id']}")->json('data.commission'));
        $this->getJson('/api/v1/repairs/commissions')->assertForbidden();
        Sanctum::actingAs($this->owner);
        $cashier = $this->staff('cashier');
        Sanctum::actingAs($cashier);
        $this->assertNull($this->getJson("/api/v1/repairs/tickets/{$first['id']}")->json('data.commission'));
    }

    public function test_the_repairs_report(): void
    {
        $this->putJson("/api/v1/repairs/commissions/{$this->tech->id}", ['type' => 'fixed', 'value' => 5000])->assertOk();
        $this->repair(30000, ['كسر زجاج', 'مش بيشحن'], part: true);
        $this->repair(20000, ['كسر زجاج'], 'iPhone 13');
        $this->postJson('/api/v1/repairs/tickets', ['customer_name' => 'x', 'customer_phone' => '01011112222', 'device_name' => 'iPhone 13', 'reported_note' => 'مش بيفتح'])->assertCreated();

        $report = $this->getJson('/api/v1/reports/repairs')->assertOk()->json('data');
        $summary = collect($report['summary'])->pluck('value', 'label');
        $this->assertSame([3, 2, 30000 + 45000 + 20000, 10000], [$summary['أجهزة دخلت'], $summary['اتسلّمت'], $summary['إيراد الصيانة'], $summary['عمولات الفنيين']]);
        $this->assertSame(95000 - 30000, $summary['المكسب (بعد القطع)']);
        $this->assertSame([['technician', 2, 2, 10000]], array_map(fn ($r) => [$r['name'], $r['tickets'], $r['repaired'], $r['commission']], $report['rows']));

        $faults = collect($this->getJson('/api/v1/reports/repairs?options[group]=fault')->json('data.rows'))->pluck('tickets', 'name');
        $this->assertSame(['كسر زجاج' => 2, 'مش بيشحن' => 1], $faults->all());

        $models = collect($this->getJson('/api/v1/reports/repairs?options[group]=model')->json('data.rows'))->keyBy('name');
        $this->assertSame([2, 1, 1], [$models['iPhone 13']['tickets'], $models['iPhone 13']['repaired'], $models['iPhone 13']['open']]);

        $this->get('/api/v1/reports/repairs/export')->assertOk();
    }
}
