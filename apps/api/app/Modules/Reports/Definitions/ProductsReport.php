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
 * What sold, per item or per category: net quantity, revenue after each line's share of the
 * invoice discount, and — with reports.profit — cost and profit. Returned units come off;
 * damaged returns keep their cost (a loss).
 */
final class ProductsReport implements Report
{
    public function key(): string
    {
        return 'products';
    }

    public function title(): string
    {
        return 'مبيعات وأرباح الأصناف';
    }

    public function description(): string
    {
        return 'إيه اللي بيتباع أكتر، وبيكسب قد إيه — لكل صنف أو لكل تصنيف.';
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
        return [['key' => 'group', 'label' => 'حسب', 'choices' => [
            ['value' => 'variant', 'label' => 'الصنف'],
            ['value' => 'category', 'label' => 'التصنيف'],
        ]]];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $byCategory = $query->option('group', 'variant') === 'category';

        $restocked = DB::table('sale_return_items')->where('restocked', true)->selectRaw('sale_item_id, sum(qty) as qty')->groupBy('sale_item_id');
        $base = $query->inPeriod(DB::table('sale_items'), 'sales.completed_at')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('product_variants', 'product_variants.id', '=', 'sale_items.variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->leftJoinSub($restocked, 'rs', 'rs.sale_item_id', '=', 'sale_items.id')
            ->where('sales.tenant_id', $query->tenantId)
            ->whereIn('sales.branch_id', $query->branchIds());

        $revenue = 'sum(coalesce(round(sale_items.line_total::numeric * sales.total / nullif(sales.subtotal, 0) * (sale_items.qty - sale_items.returned_qty) / sale_items.qty), 0))';
        $cost = 'sum(sale_items.unit_cost * (sale_items.qty - coalesce(rs.qty, 0)))';
        $select = "sum(sale_items.qty - sale_items.returned_qty) as qty, sum(sale_items.returned_qty) as returned, {$revenue} as revenue, {$cost} as cost";

        $rows = ($byCategory
            ? $base->groupBy('categories.id', 'categories.name')->selectRaw("categories.name as name, count(distinct sale_items.variant_id) as items, {$select}")
            : $base->groupBy('sale_items.variant_id')->selectRaw("max(sale_items.name) as name, max(categories.name) as category, max(sale_items.barcode) as barcode, {$select}"))
            ->orderByDesc('revenue')
            ->limit(1000)
            ->get();

        $totals = ['qty' => 0, 'returned' => 0, 'revenue' => 0, 'cost' => 0, 'profit' => 0];
        $out = [];
        foreach ($rows as $r) {
            $row = ['name' => (string) $r->name];
            if ($byCategory) {
                $row['items'] = (int) $r->items;
            } else {
                $row['category'] = (string) $r->category;
                $row['barcode'] = $r->barcode;
            }
            $row += ['qty' => (int) $r->qty, 'returned' => (int) $r->returned, 'revenue' => (int) $r->revenue];
            if ($query->withProfit) {
                $row['cost'] = (int) $r->cost;
                $row['profit'] = $row['revenue'] - $row['cost'];
                $row['margin'] = Labels::margin($row['profit'], $row['revenue']);
            }
            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k] ?? 0;
            }
            $out[] = $row;
        }

        $columns = $byCategory
            ? [new Column('name', 'التصنيف'), new Column('items', 'أصناف اتباعت', 'int')]
            : [new Column('name', 'الصنف'), new Column('category', 'التصنيف'), new Column('barcode', 'الباركود')];
        array_push($columns, new Column('qty', 'الكمية', 'int'), new Column('returned', 'مرتجع', 'int'), new Column('revenue', 'المبيعات', 'money'));
        if ($query->withProfit) {
            array_push($columns, new Column('cost', 'التكلفة', 'money'), new Column('profit', 'الربح', 'money'), new Column('margin', 'الهامش', 'percent'));
            $totals['margin'] = Labels::margin($totals['profit'], $totals['revenue']);
        }

        $summary = [
            ['label' => 'المبيعات', 'value' => $totals['revenue'], 'type' => 'money'],
            ['label' => 'قطع اتباعت', 'value' => $totals['qty'], 'type' => 'int'],
            ['label' => $byCategory ? 'تصنيفات' : 'أصناف', 'value' => count($out), 'type' => 'int'],
        ];
        if ($query->withProfit) {
            $summary[] = ['label' => 'الربح', 'value' => $totals['profit'], 'type' => 'money', 'hint' => 'هامش '.($totals['margin'] ?? 0).'%'];
        }

        $top = array_slice($out, 0, 10);

        return new ReportResult(
            columns: $columns,
            rows: $out,
            summary: $summary,
            totals: ['name' => 'الإجمالي', ...$totals],
            chart: $top === [] ? null : ['kind' => 'bars', 'label' => 'الأعلى مبيعاً', 'type' => 'money', 'points' => array_map(fn ($r) => ['label' => $r['name'], 'value' => $r['revenue']], $top)],
            notes: ['المبيعات بعد خصم الفاتورة موزّع على الأصناف، ومن غير المرتجع.'],
        );
    }
}
