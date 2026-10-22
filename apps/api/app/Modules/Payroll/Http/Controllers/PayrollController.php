<?php

declare(strict_types=1);

namespace App\Modules\Payroll\Http\Controllers;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Identity\Contracts\StaffDirectory;
use App\Modules\Payroll\Models\PayrollAdjustment;
use App\Modules\Payroll\Models\PayrollEmployee;
use App\Modules\Payroll\Models\Payslip;
use App\Modules\Payroll\Support\Period;
use App\Modules\Payroll\Support\Sheet;
use App\Modules\Repairs\Contracts\TechnicianEarnings;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * «الرواتب» (payroll.manage): who is paid how, the period's sheet, advances / bonuses / deductions,
 * closing a period into payslips and paying each from the drawer, the safe or the bank. Every
 * employee sees their own (`mine`).
 */
final class PayrollController
{
    private const FROM = ['drawer', 'safe', 'bank'];

    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly StaffDirectory $staff,
        private readonly Sheet $sheet,
    ) {}

    public function employees(): JsonResponse
    {
        $pay = PayrollEmployee::query()->get()->keyBy('user_id');

        return response()->json(['data' => array_map(fn (string $id, string $name): array => [
            'user_id' => $id,
            'name' => $name,
            'pay_cycle' => $pay[$id]->pay_cycle ?? 'monthly',
            'base_salary' => $pay[$id]->base_salary ?? 0,
            'active' => $pay[$id]->active ?? false,
        ], array_keys($all = $this->staff->all()), $all)]);
    }

    public function saveEmployee(Request $request, string $employee, Auditor $audit): JsonResponse
    {
        $user = $employee;
        $name = $this->staff->all()[$user] ?? throw new DomainRuleException('الموظف ده مش موجود.', 'employee_not_found', 404);
        $data = $request->validate([
            'pay_cycle' => ['required', Rule::in(['monthly', 'weekly'])],
            'base_salary' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'active' => ['required', 'boolean'],
        ], [], ['base_salary' => 'المرتب']);
        PayrollEmployee::query()->updateOrCreate(['user_id' => $user], ['tenant_id' => $this->tenant->idOrFail(), ...$data]);
        $audit->record('payroll.employee', "مرتب «{$name}»: ".number_format($data['base_salary'] / 100, 2).' ج '.($data['pay_cycle'] === 'weekly' ? 'في الأسبوع' : 'في الشهر').($data['active'] ? '' : ' (موقوف)'), tenantId: $this->tenant->idOrFail());

        return $this->employees();
    }

    public function sheet(Request $request): JsonResponse
    {
        $data = $request->validate(['cycle' => ['nullable', Rule::in(['monthly', 'weekly'])], 'date' => ['nullable', 'date']]);
        $period = Period::of($data['cycle'] ?? 'monthly', $data['date'] ?? null);
        $rows = $this->sheet->rows($period);

        return response()->json(['data' => $rows, 'meta' => ['period' => $period->toArray(), 'totals' => [
            'base_salary' => array_sum(array_column($rows, 'base_salary')),
            'commission' => array_sum(array_column($rows, 'commission')) - array_sum(array_column($rows, 'clawback')),
            'advances' => array_sum(array_column($rows, 'advances')),
            'net' => array_sum(array_column($rows, 'net')),
            'unpaid' => array_sum(array_map(fn (array $r) => $r['paid_at'] === null ? $r['net'] : 0, $rows)),
        ]]]);
    }

    /** The tickets behind a technician's commission in the period. */
    public function commissions(Request $request, TechnicianEarnings $earnings): JsonResponse
    {
        $data = $request->validate(['user_id' => ['required', 'uuid'], 'cycle' => ['nullable', Rule::in(['monthly', 'weekly'])], 'date' => ['nullable', 'date']]);
        $period = Period::of($data['cycle'] ?? 'monthly', $data['date'] ?? null);

        return response()->json(['data' => $earnings->lines($data['user_id'], $period->from(), $period->to())]);
    }

    /** Fixes the period: one payslip per employee still open on it, and their adjustments settled. */
    public function close(Request $request, Auditor $audit): JsonResponse
    {
        $data = $request->validate(['cycle' => ['required', Rule::in(['monthly', 'weekly'])], 'date' => ['required', 'date']]);
        $period = Period::of($data['cycle'], $data['date']);
        $by = $request->user()?->getAttribute('name');

        $count = DB::transaction(function () use ($period, $by, $audit): int {
            $open = array_filter($this->sheet->rows($period), fn (array $r) => ! $r['closed']);
            foreach ($open as $row) {
                $slip = Payslip::create([
                    'tenant_id' => $this->tenant->idOrFail(),
                    'user_id' => $row['user_id'],
                    'user_name' => $row['name'],
                    'cycle' => $period->cycle,
                    'starts_on' => $period->starts->toDateString(),
                    'ends_on' => $period->ends->toDateString(),
                    ...array_intersect_key($row, array_flip(['base_salary', 'tickets', 'commission', 'clawback', 'bonuses', 'deductions', 'advances', 'net'])),
                    'closed_by_name' => $by,
                ]);
                PayrollAdjustment::query()->whereIn('id', $row['adjustment_ids'])->update(['payslip_id' => $slip->id]);
            }
            if ($open !== []) {
                $audit->record('payroll.closed', "قفل رواتب {$period->label()}: ".count($open).' موظف بصافي '.number_format(array_sum(array_column($open, 'net')) / 100, 2).' ج', tenantId: $this->tenant->idOrFail());
            }

            return count($open);
        });
        if ($count === 0) {
            throw new DomainRuleException('مفيش حد لسه مفتوح في الفترة دي.', 'payroll_nothing_to_close');
        }

        return $this->sheet(new Request(['cycle' => $period->cycle, 'date' => $period->starts->toDateString()]));
    }

    /** Pays one payslip: out of the payer's drawer (their open shift here), or the safe / a bank transfer. */
    public function pay(Request $request, Payslip $payslip, CashDrawer $drawer, CurrentBranch $branch, Auditor $audit): JsonResponse
    {
        $data = $request->validate(['from' => ['required', Rule::in(self::FROM)]]);
        DB::transaction(function () use ($payslip, $data, $drawer, $branch, $audit, $request): void {
            $slip = Payslip::query()->lockForUpdate()->findOrFail($payslip->id);
            if ($slip->paid_at !== null) {
                throw new DomainRuleException('المرتب ده اتصرف قبل كده.', 'payslip_paid');
            }
            if ($data['from'] === 'drawer' && $slip->net > 0) {
                $drawer->record($branch->idOrFail(), DrawerEntry::Payroll, 'cash', -$slip->net, 'payroll_payslip', $slip->id, "مرتب {$slip->user_name}");
            }
            $slip->update(['paid_at' => now(), 'paid_from' => $data['from'], 'paid_by_name' => $request->user()?->getAttribute('name')]);
            $audit->record('payroll.paid', "صرف مرتب «{$slip->user_name}» ".number_format($slip->net / 100, 2).' ج من '.['drawer' => 'الدرج', 'safe' => 'الخزنة', 'bank' => 'البنك'][$data['from']], tenantId: $this->tenant->idOrFail());
        });

        return response()->json(['data' => $payslip->refresh()->toArray()]);
    }

    public function adjustments(Request $request): JsonResponse
    {
        $data = $request->validate(['user_id' => ['nullable', 'uuid'], 'open' => ['nullable', 'boolean']]);
        $rows = PayrollAdjustment::query()
            ->when(isset($data['user_id']), fn ($q) => $q->where('user_id', $data['user_id']))
            ->when($request->boolean('open'), fn ($q) => $q->whereNull('payslip_id'))
            ->orderByDesc('on_date')->orderByDesc('created_at')->limit(200)->get();

        return response()->json(['data' => $rows->map(fn (PayrollAdjustment $a) => $a->toArray())->all()]);
    }

    /** An advance (paid now: drawer / safe / bank), a bonus or a deduction; it lands on the next payslip. */
    public function addAdjustment(Request $request, CashDrawer $drawer, CurrentBranch $branch, Auditor $audit): JsonResponse
    {
        $data = $request->validate([
            'user_id' => ['required', 'uuid'],
            'kind' => ['required', Rule::in(array_keys(PayrollAdjustment::KINDS))],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:200'],
            'on_date' => ['nullable', 'date'],
            'from' => ['required_if:kind,advance', 'nullable', Rule::in(self::FROM)],
        ], [], ['amount' => 'المبلغ', 'from' => 'اتصرفت منين']);
        $name = $this->staff->all()[$data['user_id']] ?? throw new DomainRuleException('الموظف ده مش موجود.', 'employee_not_found', 404);

        $adjustment = DB::transaction(function () use ($data, $name, $drawer, $branch, $audit, $request): PayrollAdjustment {
            $adjustment = PayrollAdjustment::create([
                'tenant_id' => $this->tenant->idOrFail(),
                'user_id' => $data['user_id'],
                'kind' => $data['kind'],
                'amount' => (int) $data['amount'],
                'note' => $data['note'] ?? null,
                'on_date' => $data['on_date'] ?? now('Africa/Cairo')->toDateString(),
                'paid_from' => $data['kind'] === 'advance' ? $data['from'] : null,
                'branch_id' => $branch->id(),
                'created_by_name' => $request->user()?->getAttribute('name'),
            ]);
            if ($data['kind'] === 'advance' && $data['from'] === 'drawer') {
                $drawer->record($branch->idOrFail(), DrawerEntry::Payroll, 'cash', -$adjustment->amount, 'payroll_advance', $adjustment->id, "سلفة {$name}");
            }
            $audit->record('payroll.adjustment', PayrollAdjustment::KINDS[$data['kind']]." لـ «{$name}» ".number_format($adjustment->amount / 100, 2).' ج'.($adjustment->note ? " ({$adjustment->note})" : ''), tenantId: $this->tenant->idOrFail());

            return $adjustment;
        });

        return response()->json(['data' => $adjustment->toArray()], 201);
    }

    /** A bonus or deduction not on a payslip yet (an advance was paid out: it stays). */
    public function removeAdjustment(PayrollAdjustment $adjustment, Auditor $audit): JsonResponse
    {
        if ($adjustment->payslip_id !== null || $adjustment->kind === 'advance') {
            throw new DomainRuleException($adjustment->kind === 'advance' ? 'السلفة اتصرفت؛ بتتخصم من المرتب.' : 'دي نزلت في مرتب اتقفل خلاص.', 'adjustment_locked');
        }
        $adjustment->delete();
        $audit->record('payroll.adjustment_removed', 'شال '.PayrollAdjustment::KINDS[$adjustment->kind].' '.number_format($adjustment->amount / 100, 2).' ج', tenantId: $this->tenant->idOrFail());

        return response()->json(['data' => null]);
    }

    /** The signed-in employee's own: this period so far, their payslips and open adjustments. */
    public function mine(Request $request): JsonResponse
    {
        $me = (string) $request->user()?->getAuthIdentifier();
        $cycle = PayrollEmployee::query()->where('user_id', $me)->value('pay_cycle') ?? 'monthly';
        $period = Period::of($cycle);

        return response()->json(['data' => [
            'period' => $period->toArray(),
            'current' => $this->sheet->rows($period, [$me])[0] ?? null,
            'payslips' => Payslip::query()->where('user_id', $me)->orderByDesc('starts_on')->limit(24)->get()->map(fn (Payslip $p) => $p->toArray())->all(),
            'adjustments' => PayrollAdjustment::query()->where('user_id', $me)->whereNull('payslip_id')->orderByDesc('on_date')->get()->map(fn (PayrollAdjustment $a) => $a->toArray())->all(),
        ]]);
    }
}
