<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Illuminate\Support\Facades\DB;

/**
 * Per supplier: what was bought in the period and what went back (units put in the returns bin
 * against that supplier, plus purchase returns), as qty, value and a return rate — to pick the
 * best suppliers.
 */
final class SupplierReturnRateReport implements Report
{
    public function key(): string
    {
        return 'supplier_return_rate';
    }

    public function title(): string
    {
        return 'نسبة المرتجع لكل مورد';
    }

    public function description(): string
    {
        return 'اشتريت قد إيه من كل مورد ورجّعتله قد إيه — عشان تختار أحسن مورد.';
    }

    public function group(): string
    {
        return 'stock';
    }

    public function permission(): ?string
    {
        return 'supplier_returns.view';
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
        $bought = $query->inPeriod(DB::table('purchases'), 'purchases.created_at')
            ->join('purchase_items', 'purchase_items.purchase_id', '=', 'purchases.id')
            ->where('purchases.tenant_id', $query->tenantId)
            ->whereIn('purchases.branch_id', $query->branchIds())
            ->groupBy('purchases.supplier_id')
            ->selectRaw('purchases.supplier_id, sum(purchase_items.qty) as qty, sum(purchase_items.qty * purchase_items.net_unit_cost) as value');

        $binned = $query->inPeriod(DB::table('supplier_return_items'), 'created_at')
            ->where('tenant_id', $query->tenantId)
            ->whereIn('branch_id', $query->branchIds())
            ->where('source_type', 'supplier')
            // A unit put back into stock from the bin wasn't really defective.
            ->where(fn ($q) => $q->whereNull('outcome')->orWhere('outcome', '!=', 'restocked'))
            ->groupBy('source_id')
            ->selectRaw('source_id as supplier_id, sum(qty) as qty, sum(qty * unit_cost) as value');

        $purchaseReturns = $query->inPeriod(DB::table('purchase_returns'), 'purchase_returns.created_at')
            ->join('purchase_return_items', 'purchase_return_items.purchase_return_id', '=', 'purchase_returns.id')
            ->where('purchase_returns.tenant_id', $query->tenantId)
            ->whereIn('purchase_returns.branch_id', $query->branchIds())
            ->groupBy('purchase_returns.supplier_id')
            ->selectRaw('purchase_returns.supplier_id, sum(purchase_return_items.qty) as qty, sum(purchase_return_items.line_total) as value');

        $rows = DB::table('suppliers')
            ->leftJoinSub($bought, 'b', 'b.supplier_id', '=', 'suppliers.id')
            ->leftJoinSub($binned, 'r', 'r.supplier_id', '=', 'suppliers.id')
            ->leftJoinSub($purchaseReturns, 'p', 'p.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.tenant_id', $query->tenantId)
            ->where(fn ($q) => $q->whereNotNull('b.supplier_id')->orWhereNotNull('r.supplier_id')->orWhereNotNull('p.supplier_id'))
            ->select('suppliers.name', 'b.qty as bought_qty', 'b.value as bought_value', 'r.qty as bin_qty', 'r.value as bin_value', 'p.qty as pr_qty', 'p.value as pr_value')
            ->get();

        $out = [];
        $totals = ['bought_qty' => 0, 'bought_value' => 0, 'returned_qty' => 0, 'returned_value' => 0];
        foreach ($rows as $r) {
            $row = [
                'name' => (string) $r->name,
                'bought_qty' => (int) ($r->bought_qty ?? 0),
                'bought_value' => (int) ($r->bought_value ?? 0),
                'returned_qty' => (int) ($r->bin_qty ?? 0) + (int) ($r->pr_qty ?? 0),
                'returned_value' => (int) ($r->bin_value ?? 0) + (int) ($r->pr_value ?? 0),
            ];
            $row['rate'] = $row['bought_qty'] > 0 ? round($row['returned_qty'] * 100 / $row['bought_qty'], 1) : null;
            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k];
            }
            if (! $query->withCost) {
                $row['bought_value'] = null;
                $row['returned_value'] = null;
            }
            $out[] = $row;
        }
        usort($out, fn (array $a, array $b) => ($b['rate'] ?? -1) <=> ($a['rate'] ?? -1));

        $rate = $totals['bought_qty'] > 0 ? round($totals['returned_qty'] * 100 / $totals['bought_qty'], 1) : null;
        $columns = [new Column('name', 'المورد'), new Column('bought_qty', 'اشتريت (قطعة)', 'int')];
        if ($query->withCost) {
            $columns[] = new Column('bought_value', 'قيمة المشتريات', 'money');
        }
        $columns[] = new Column('returned_qty', 'رجّعت (قطعة)', 'int');
        if ($query->withCost) {
            $columns[] = new Column('returned_value', 'قيمة المرتجع', 'money');
        }
        $columns[] = new Column('rate', 'نسبة المرتجع', 'percent');

        return new ReportResult(
            columns: $columns,
            rows: $out,
            summary: [
                ['label' => 'قطع اشتريتها', 'value' => $totals['bought_qty'], 'type' => 'int'],
                ['label' => 'قطع رجّعتها', 'value' => $totals['returned_qty'], 'type' => 'int'],
                ['label' => 'نسبة المرتجع', 'value' => $rate, 'type' => 'percent'],
            ],
            totals: ['name' => 'الإجمالي', ...$totals, 'rate' => $rate, ...($query->withCost ? [] : ['bought_value' => null, 'returned_value' => null])],
            chart: $out === [] ? null : ['kind' => 'bars', 'label' => 'نسبة المرتجع %', 'type' => 'percent', 'points' => array_map(
                fn (array $r) => ['label' => $r['name'], 'value' => $r['rate'] ?? 0],
                array_slice($out, 0, 10),
            )],
            notes: ['المرتجع = القطع اللي دخلت سلة المرتجعات على المورد ده (غير اللي رجعت المخزون) + مرتجعات فواتير الشراء، في نفس الفترة.'],
        );
    }
}
