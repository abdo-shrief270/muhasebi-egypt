<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Support;

use App\Modules\Identity\Contracts\StaffDirectory;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollEmployee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Repairs\Contracts\TechnicianEarnings;

/**
 * A period's pay, per employee on that cycle: the base salary, the technicians' commission from
 * tickets delivered in it (less warranty returns taken back), the bonuses, deductions and advances
 * not yet on a payslip (dated up to the period's end) — or, once closed, the payslips as they were.
 */
final class Sheet
{
    public function __construct(
        private readonly StaffDirectory $staff,
        private readonly TechnicianEarnings $earnings,
    ) {}

    /**
     * @param  list<string>|null  $only  just these users (an employee's own view)
     * @return list<array<string, mixed>>
     */
    public function rows(Period $period, ?array $only = null): array
    {
        $names = $this->staff->all();
        $closed = Payslip::query()->where('cycle', $period->cycle)->whereDate('starts_on', $period->starts->toDateString())
            ->when($only !== null, fn ($q) => $q->whereIn('user_id', $only))
            ->get()->keyBy('user_id');
        $employees = PayrollEmployee::query()->where('pay_cycle', $period->cycle)->where('active', true)
            ->when($only !== null, fn ($q) => $q->whereIn('user_id', $only))
            ->whereNotIn('user_id', $closed->keys()->all())
            ->get()
            ->filter(fn (PayrollEmployee $e) => isset($names[$e->user_id]));
        $ids = $employees->pluck('user_id')->values()->all();
        $earned = $ids === [] ? [] : $this->earnings->between($ids, $period->from(), $period->to());
        $adjustments = PayrollAdjustment::query()->whereIn('user_id', $ids)->whereNull('payslip_id')
            ->whereDate('on_date', '<=', $period->ends->toDateString())->get()->groupBy('user_id');

        $rows = $closed->map(fn (Payslip $p) => $p->toArray())->values()->all();
        foreach ($employees as $e) {
            $adj = $adjustments[$e->user_id] ?? collect();
            $sum = fn (string $kind) => (int) $adj->where('kind', $kind)->sum('amount');
            $row = [
                'id' => null,
                'user_id' => $e->user_id,
                'name' => $names[$e->user_id],
                'cycle' => $period->cycle,
                'starts_on' => $period->starts->toDateString(),
                'ends_on' => $period->ends->toDateString(),
                'base_salary' => $e->base_salary,
                'tickets' => $earned[$e->user_id]['tickets'] ?? 0,
                'commission' => $earned[$e->user_id]['commission'] ?? 0,
                'clawback' => $earned[$e->user_id]['clawback'] ?? 0,
                'bonuses' => $sum('bonus'),
                'deductions' => $sum('deduction'),
                'advances' => $sum('advance'),
                'paid_at' => null,
                'paid_from' => null,
                'paid_by_name' => null,
                'closed' => false,
                'adjustment_ids' => $adj->pluck('id')->all(),
            ];
            $row['net'] = $row['base_salary'] + $row['commission'] - $row['clawback'] + $row['bonuses'] - $row['deductions'] - $row['advances'];
            $rows[] = $row;
        }
        usort($rows, fn (array $a, array $b) => strcmp($a['name'], $b['name']));

        return $rows;
    }
}
