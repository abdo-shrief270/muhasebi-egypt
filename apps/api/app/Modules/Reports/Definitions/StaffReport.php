<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Labels;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Illuminate\Support\Facades\DB;

/** Sales per cashier: invoices, discounts given, returns and profit. */
final class StaffReport implements Report
{
    public function key(): string
    {
        return 'staff';
    }

    public function title(): string
    {
        return 'مبيعات الموظفين';
    }

    public function description(): string
    {
        return 'كل كاشير باع قد إيه، وادّى خصومات قد إيه، ومرتجعاته.';
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
        return [];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $lineDiscounts = DB::table('sale_items')->selectRaw('sale_id, sum(discount) as line_discount')->groupBy('sale_id');
        $rows = $query->inPeriod(DB::table('sales'), 'sales.completed_at')
            ->leftJoinSub($lineDiscounts, 'ld', 'ld.sale_id', '=', 'sales.id')
            ->where('sales.tenant_id', $query->tenantId)
            ->whereIn('sales.branch_id', $query->branchIds())
            ->groupBy('sales.cashier_id')
            ->selectRaw('max(sales.cashier_name) as name, count(*) as invoices, sum(sales.discount + coalesce(ld.line_discount, 0)) as discounts,
                sum(sales.refunded) as returns, sum(sales.total - sales.refunded) as net, sum(sales.cost_total - sales.refunded_cost) as cost')
            ->orderByDesc('net')
            ->get();

        $totals = ['invoices' => 0, 'discounts' => 0, 'returns' => 0, 'net' => 0, 'profit' => 0];
        $out = [];
        foreach ($rows as $r) {
            $row = [
                'name' => (string) ($r->name ?? '—'),
                'invoices' => (int) $r->invoices,
                'net' => (int) $r->net,
                'average' => (int) $r->invoices > 0 ? intdiv((int) $r->net, (int) $r->invoices) : 0,
                'discounts' => (int) $r->discounts,
                'returns' => (int) $r->returns,
            ];
            if ($query->withProfit) {
                $row['profit'] = $row['net'] - (int) $r->cost;
                $row['margin'] = Labels::margin($row['profit'], $row['net']);
            }
            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k] ?? 0;
            }
            $out[] = $row;
        }

        $columns = [
            new Column('name', 'الموظف'),
            new Column('invoices', 'الفواتير', 'int'),
            new Column('net', 'صافي المبيعات', 'money'),
            new Column('average', 'متوسط الفاتورة', 'money'),
            new Column('discounts', 'الخصومات', 'money'),
            new Column('returns', 'المرتجعات', 'money'),
        ];
        if ($query->withProfit) {
            array_push($columns, new Column('profit', 'الربح', 'money'), new Column('margin', 'الهامش', 'percent'));
        }

        return new ReportResult(
            columns: $columns,
            rows: $out,
            summary: [
                ['label' => 'صافي المبيعات', 'value' => $totals['net'], 'type' => 'money'],
                ['label' => 'الخصومات', 'value' => $totals['discounts'], 'type' => 'money'],
                ['label' => 'الموظفين', 'value' => count($out), 'type' => 'int'],
            ],
            totals: ['name' => 'الإجمالي', ...$totals, 'average' => $totals['invoices'] > 0 ? intdiv($totals['net'], $totals['invoices']) : 0],
            chart: $out === [] ? null : ['kind' => 'bars', 'label' => 'صافي المبيعات', 'type' => 'money', 'points' => array_map(fn ($r) => ['label' => $r['name'], 'value' => $r['net']], $out)],
        );
    }
}
