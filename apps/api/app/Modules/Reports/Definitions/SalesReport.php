<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Labels;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Illuminate\Support\Facades\DB;

/**
 * Sales per day (or month): what was sold, discounted and returned, and — with reports.profit —
 * the cost of what stayed sold and the profit, less the period's expenses for the net.
 * Returns count against the day of the sale they came from.
 */
final class SalesReport implements Report
{
    public function key(): string
    {
        return 'sales';
    }

    public function title(): string
    {
        return 'المبيعات والأرباح';
    }

    public function description(): string
    {
        return 'المبيعات والخصومات والمرتجعات والربح يوم بيوم أو شهر بشهر.';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function permission(): ?string
    {
        return null;
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
        return [['key' => 'group', 'label' => 'التجميع', 'choices' => [
            ['value' => 'day', 'label' => 'يوم بيوم'],
            ['value' => 'month', 'label' => 'شهر بشهر'],
        ]]];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $byMonth = $query->option('group', 'day') === 'month';
        $day = ReportQuery::localDay('sales.completed_at');
        $bucket = $byMonth ? "date_trunc('month', {$day})::date" : $day;

        $lineDiscounts = DB::table('sale_items')->selectRaw('sale_id, sum(discount) as line_discount')->groupBy('sale_id');
        $rows = $query->inPeriod(DB::table('sales'), 'sales.completed_at')
            ->leftJoinSub($lineDiscounts, 'ld', 'ld.sale_id', '=', 'sales.id')
            ->where('sales.tenant_id', $query->tenantId)
            ->whereIn('sales.branch_id', $query->branchIds())
            ->groupByRaw($bucket)
            ->selectRaw("{$bucket} as bucket, count(*) as invoices, sum(sales.total) as sales, sum(sales.discount + coalesce(ld.line_discount, 0)) as discounts,
                sum(sales.refunded) as returns, sum(sales.total - sales.refunded) as net, sum(sales.cost_total - sales.refunded_cost) as cost, sum(sales.credit) as credit")
            ->get()
            ->keyBy(fn ($r) => (string) $r->bucket);

        $out = [];
        $totals = ['invoices' => 0, 'sales' => 0, 'discounts' => 0, 'returns' => 0, 'net' => 0, 'cost' => 0, 'profit' => 0, 'credit' => 0];
        $points = [];
        for ($d = $byMonth ? $query->from->startOfMonth() : $query->from->startOfDay(); $d <= $query->to; $d = $byMonth ? $d->addMonth() : $d->addDay()) {
            $key = $d->toDateString();
            $r = $rows->get($key);
            $row = [
                'date' => $key,
                'invoices' => (int) ($r->invoices ?? 0),
                'sales' => (int) ($r->sales ?? 0),
                'discounts' => (int) ($r->discounts ?? 0),
                'returns' => (int) ($r->returns ?? 0),
                'net' => (int) ($r->net ?? 0),
                'credit' => (int) ($r->credit ?? 0),
            ];
            if ($query->withProfit) {
                $row['cost'] = (int) ($r->cost ?? 0);
                $row['profit'] = $row['net'] - $row['cost'];
                $row['margin'] = Labels::margin($row['profit'], $row['net']);
            }
            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k] ?? 0;
            }
            $out[] = $row;
            $points[] = ['label' => $key, 'date' => $key, 'value' => $row['net']];
        }

        $columns = [
            new Column('date', $byMonth ? 'الشهر' : 'اليوم', $byMonth ? 'month' : 'date'),
            new Column('invoices', 'الفواتير', 'int'),
            new Column('sales', 'المبيعات', 'money'),
            new Column('discounts', 'الخصومات', 'money'),
            new Column('returns', 'المرتجعات', 'money'),
            new Column('net', 'الصافي', 'money'),
            new Column('credit', 'منها آجل', 'money'),
        ];
        if ($query->withProfit) {
            $columns[] = new Column('cost', 'التكلفة', 'money');
            $columns[] = new Column('profit', 'الربح', 'money');
            $columns[] = new Column('margin', 'الهامش', 'percent');
        }

        $summary = [
            ['label' => 'صافي المبيعات', 'value' => $totals['net'], 'type' => 'money'],
            ['label' => 'الفواتير', 'value' => $totals['invoices'], 'type' => 'int'],
            ['label' => 'متوسط الفاتورة', 'value' => $totals['invoices'] > 0 ? intdiv($totals['sales'], $totals['invoices']) : 0, 'type' => 'money'],
            ['label' => 'المرتجعات', 'value' => $totals['returns'], 'type' => 'money'],
        ];
        $notes = ['المرتجع بيتحسب في يوم الفاتورة الأصلية.'];
        if ($query->withProfit) {
            $expenses = -(int) $query->inPeriod(DB::table('cash_movements'), 'created_at')
                ->where('tenant_id', $query->tenantId)
                ->whereIn('branch_id', $query->branchIds())
                ->where('type', 'expense')
                ->sum('amount');
            $totals['margin'] = Labels::margin($totals['profit'], $totals['net']);
            array_push(
                $summary,
                ['label' => 'مجمل الربح', 'value' => $totals['profit'], 'type' => 'money', 'hint' => 'هامش '.($totals['margin'] ?? 0).'%'],
                ['label' => 'المصروفات', 'value' => $expenses, 'type' => 'money'],
                ['label' => 'صافي الربح', 'value' => $totals['profit'] - $expenses, 'type' => 'money', 'hint' => 'مجمل الربح ناقص المصروفات'],
            );
            $notes[] = 'التكلفة = تكلفة البضاعة اللي اتباعت فعلاً (أول وارد أول صادر). التالف في المرتجع بيفضل تكلفة.';
            $notes[] = 'المصروفات = اللي اتسجّل من درج الورديات في الفترة.';
        }

        return new ReportResult(
            columns: $columns,
            rows: $out,
            summary: $summary,
            totals: ['date' => 'الإجمالي', ...$totals],
            chart: ['kind' => 'daily', 'label' => 'صافي المبيعات', 'type' => 'money', 'points' => $points],
            notes: $notes,
        );
    }
}
