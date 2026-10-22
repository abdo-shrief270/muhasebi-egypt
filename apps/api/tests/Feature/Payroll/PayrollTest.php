<?php

namespace Tests\Feature\Payroll;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\Concerns\SellsInAShop;
use Tests\TestCase;

class PayrollTest extends TestCase
{
    use RefreshDatabase, SellsInAShop;

    protected function setUp(): void
    {
        parent::setUp();
        $this->openShopWithStock();
        $this->openShift();
    }

    /** A repair by the technician with the given labor, delivered with a warranty. */
    private function repair(string $tech, int $labor): array
    {
        $id = $this->postJson('/api/v1/repairs/tickets', ['customer_name' => 'عميل', 'customer_phone' => '01234567890', 'device_name' => 'iPhone 13', 'reported_note' => 'x', 'technician_id' => $tech])->assertCreated()->json('data.id');
        $url = "/api/v1/repairs/tickets/{$id}";
        $this->postJson("{$url}/status", ['status' => 'repairing'])->assertOk();
        $this->patchJson($url, ['labor' => $labor])->assertOk();
        $this->postJson("{$url}/status", ['status' => 'ready'])->assertOk();

        return $this->postJson("{$url}/deliver", ['payments' => [['method' => 'cash', 'amount' => $labor]], 'warranty_days' => 30])->assertOk()->json('data');
    }

    public function test_a_month_of_pay_with_commission_advances_and_a_warranty_clawback(): void
    {
        $tech = $this->staff('technician');
        $cashier = $this->staff('cashier');
        $this->putJson("/api/v1/payroll/employees/{$tech->id}", ['pay_cycle' => 'monthly', 'base_salary' => 500000, 'active' => true])->assertOk();
        $this->putJson("/api/v1/payroll/employees/{$cashier->id}", ['pay_cycle' => 'weekly', 'base_salary' => 150000, 'active' => true])->assertOk();

        // 30% of the labor; a warranty return takes it back.
        $this->putJson("/api/v1/repairs/commissions/{$tech->id}", ['labor_rate' => 3000, 'warranty_clawback' => true])->assertOk();
        $first = $this->repair($tech->id, 30000);
        $this->repair($tech->id, 20000);
        $this->postJson("/api/v1/repairs/tickets/{$first['id']}/warranty")->assertCreated();

        // An advance from the drawer, a bonus, a deduction.
        $this->postJson('/api/v1/payroll/adjustments', ['user_id' => $tech->id, 'kind' => 'advance', 'amount' => 100000, 'from' => 'drawer'])->assertCreated();
        $this->postJson('/api/v1/payroll/adjustments', ['user_id' => $tech->id, 'kind' => 'bonus', 'amount' => 20000, 'note' => 'شغل كويس'])->assertCreated();
        $deduction = $this->postJson('/api/v1/payroll/adjustments', ['user_id' => $tech->id, 'kind' => 'deduction', 'amount' => 5000])->assertCreated()->json('data');
        $this->deleteJson("/api/v1/payroll/adjustments/{$deduction['id']}")->assertOk();

        $sheet = $this->getJson('/api/v1/payroll/sheet?cycle=monthly')->assertOk()->json();
        $row = collect($sheet['data'])->firstWhere('user_id', $tech->id);
        // 5000 + (90 + 60) − 90 back + 200 bonus − 1000 advance.
        $this->assertSame([2, 15000, 9000, 20000, 100000, 500000 + 15000 - 9000 + 20000 - 100000], [$row['tickets'], $row['commission'], $row['clawback'], $row['bonuses'], $row['advances'], $row['net']]);
        $this->assertNull(collect($sheet['data'])->firstWhere('user_id', $cashier->id), 'weekly staff are on the weekly sheet');
        $this->assertCount(1, $this->getJson('/api/v1/payroll/sheet?cycle=weekly')->json('data'));
        $this->assertCount(3, $this->getJson("/api/v1/payroll/commissions?user_id={$tech->id}")->json('data'));

        // Close and pay from the drawer.
        $closed = collect($this->postJson('/api/v1/payroll/close', ['cycle' => 'monthly', 'date' => now()->toDateString()])->assertOk()->json('data'))->firstWhere('user_id', $tech->id);
        $this->assertTrue($closed['closed']);
        $this->postJson('/api/v1/payroll/close', ['cycle' => 'monthly', 'date' => now()->toDateString()])->assertStatus(422)->assertJsonPath('code', 'payroll_nothing_to_close');
        $this->postJson("/api/v1/payroll/payslips/{$closed['id']}/pay", ['from' => 'drawer'])->assertOk()->assertJsonPath('data.paid_from', 'drawer');
        $this->postJson("/api/v1/payroll/payslips/{$closed['id']}/pay", ['from' => 'safe'])->assertStatus(422);
        $this->assertDatabaseHas('cash_movements', ['ref_type' => 'payroll_payslip', 'amount' => -$closed['net']]);
        $this->assertDatabaseHas('cash_movements', ['ref_type' => 'payroll_advance', 'amount' => -100000]);

        // The technician sees their own pay only.
        Sanctum::actingAs($tech);
        $mine = $this->getJson('/api/v1/payroll/mine')->assertOk()->json('data');
        $this->assertSame($closed['net'], $mine['payslips'][0]['net']);
        $this->getJson('/api/v1/payroll/sheet')->assertForbidden();
    }

    public function test_managers_dont_get_payroll_by_default(): void
    {
        Sanctum::actingAs($this->staff('manager'));
        $this->getJson('/api/v1/payroll/employees')->assertForbidden();
    }
}
