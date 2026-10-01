<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/**
 * Installment plans: what was financed and collected in the period, and who is late now (by how
 * long). Plans are shop-wide like the customer accounts they split; branches filter by where a
 * plan was made / a payment was taken.
 */
final class InstallmentsReport implements Report
{
    public function key(): string
    {
        return 'installments';
    }

    public function title(): string
    {
        return 'التقسيط';
    }

    public function description(): string
    {
        return 'الخطط الجديدة والمحصّل في الفترة، والمتأخرين دلوقتي.';
    }

    public function group(): string
    {
        return 'money';
    }

    public function permission(): ?string
    {
        return 'installments.collect';
    }

    public function usesDates(): bool
    {
        return true;
    }

    public function usesBranches(): bool
    {
        return true;
    }

    public function options(): array
    {
        return [[
            'key' => 'view',
            'label' => 'العرض',
            'choices' => [
                ['value' => 'plans', 'label' => 'الخطط الجديدة في الفترة'],
                ['value' => 'late', 'label' => 'المتأخرين دلوقتي'],
            ],
        ]];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $today = CarbonImmutable::now(ReportQuery::TZ)->toDateString();
        $plans = $query->inPeriod(DB::table('installment_plans'), 'created_at')
            ->where('tenant_id', $query->tenantId)
            ->whereIn('branch_id', $query->branchIds());
        $collected = (int) $query->inPeriod(DB::table('installment_payments'), 'created_at')
            ->where('tenant_id', $query->tenantId)
            ->where(fn ($q) => $q->whereIn('branch_id', $query->branchIds())->orWhereNull('branch_id'))
            ->sum('amount');
        $daily = $query->inPeriod(DB::table('installment_payments'), 'created_at')
            ->where('tenant_id', $query->tenantId)
            ->where(fn ($q) => $q->whereIn('branch_id', $query->branchIds())->orWhereNull('branch_id'))
            ->selectRaw(ReportQuery::localDay('created_at').' as day, sum(amount) as amount')
            ->groupBy('day')->orderBy('day')->get();
        $late = DB::table('installment_items as i')
            ->join('installment_plans as p', 'p.id', '=', 'i.plan_id')
            ->where('p.tenant_id', $query->tenantId)
            ->where('p.status', 'active')
            ->whereIn('p.branch_id', $query->branchIds())
            ->whereColumn('i.paid', '<', 'i.amount')
            ->where('i.due_on', '<', $today);
        $outstanding = (int) DB::table('installment_plans')->where('tenant_id', $query->tenantId)->where('status', 'active')->whereIn('branch_id', $query->branchIds())->sum(DB::raw('total - paid'));
        $lateAmount = (int) (clone $late)->sum(DB::raw('i.amount - i.paid'));

        if ($query->option('view', 'plans') === 'late') {
            $rows = (clone $late)
                ->groupBy('p.id', 'p.number', 'p.customer_name', 'p.customer_phone', 'p.total', 'p.paid')
                ->selectRaw('p.number, p.customer_name, p.customer_phone, p.total - p.paid as remaining, count(*) as late_count, sum(i.amount - i.paid) as late_amount, min(i.due_on) as oldest')
                ->orderBy('oldest')
                ->get();
            $out = $rows->map(fn ($r) => [
                'plan' => sprintf('INS-%05d', $r->number),
                'customer' => (string) $r->customer_name,
                'phone' => $r->customer_phone ? preg_replace('/^\+20/', '0', (string) $r->customer_phone) : null,
                'late_count' => (int) $r->late_count,
                'late_amount' => (int) $r->late_amount,
                'days' => (int) CarbonImmutable::parse($r->oldest)->diffInDays(CarbonImmutable::parse($today)),
                'remaining' => (int) $r->remaining,
            ])->all();
            $columns = [
                new Column('plan', 'التقسيط'), new Column('customer', 'العميل'), new Column('phone', 'الموبايل'),
                new Column('late_count', 'أقساط متأخرة', 'int'), new Column('late_amount', 'المتأخر', 'money'),
                new Column('days', 'أيام التأخير', 'int'), new Column('remaining', 'الباقي كله', 'money'),
            ];
            $totals = ['plan' => 'الإجمالي', 'late_count' => array_sum(array_column($out, 'late_count')), 'late_amount' => array_sum(array_column($out, 'late_amount')), 'remaining' => array_sum(array_column($out, 'remaining'))];
        } else {
            $rows = (clone $plans)->orderBy('number')->get(['number', 'customer_name', 'sale_reference', 'principal', 'markup', 'total', 'paid', 'count', 'status', 'created_at']);
            $out = $rows->map(fn ($r) => [
                'plan' => sprintf('INS-%05d', $r->number),
                'date' => CarbonImmutable::parse($r->created_at)->toIso8601String(),
                'customer' => (string) $r->customer_name,
                'sale' => $r->sale_reference,
                'count' => (int) $r->count,
                'principal' => (int) $r->principal,
                'markup' => (int) $r->markup,
                'paid' => (int) $r->paid,
                'remaining' => $r->status === 'active' ? (int) $r->total - (int) $r->paid : 0,
                'status' => match ($r->status) {
                    'completed' => 'خلص', 'cancelled' => 'اتلغى', default => 'شغّال'
                },
            ])->all();
            $columns = [
                new Column('plan', 'التقسيط'), new Column('date', 'اتعمل', 'datetime'), new Column('customer', 'العميل'), new Column('sale', 'الفاتورة'),
                new Column('count', 'الأقساط', 'int'), new Column('principal', 'المبلغ', 'money'), new Column('markup', 'الفوايد', 'money'),
                new Column('paid', 'اتدفع', 'money'), new Column('remaining', 'الباقي', 'money'), new Column('status', 'الحالة'),
            ];
            $totals = ['plan' => 'الإجمالي', 'principal' => array_sum(array_column($out, 'principal')), 'markup' => array_sum(array_column($out, 'markup')), 'paid' => array_sum(array_column($out, 'paid')), 'remaining' => array_sum(array_column($out, 'remaining'))];
        }

        return new ReportResult(
            columns: $columns,
            rows: array_values($out),
            summary: [
                ['label' => 'خطط جديدة', 'value' => (clone $plans)->count(), 'type' => 'int'],
                ['label' => 'اتقسّط', 'value' => (int) (clone $plans)->sum('principal'), 'type' => 'money'],
                ['label' => 'فوايد التقسيط', 'value' => (int) (clone $plans)->sum('markup'), 'type' => 'money', 'hint' => 'على الخطط اللي اتعملت في الفترة'],
                ['label' => 'اتحصّل', 'value' => $collected, 'type' => 'money'],
                ['label' => 'الباقي عند العملاء', 'value' => $outstanding, 'type' => 'money', 'hint' => 'دلوقتي'],
                ['label' => 'متأخر', 'value' => $lateAmount, 'type' => 'money', 'hint' => 'دلوقتي'],
            ],
            totals: $totals,
            chart: $daily->isEmpty() ? null : ['kind' => 'daily', 'label' => 'التحصيل', 'type' => 'money', 'points' => $daily->map(fn ($d) => ['label' => (string) $d->day, 'date' => (string) $d->day, 'value' => (int) $d->amount])->all()],
        );
    }
}
