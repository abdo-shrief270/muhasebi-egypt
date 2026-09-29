<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Illuminate\Support\Facades\DB;

/** Stock on hand now: quantities and — with products.view_cost — its value at cost, per category or item. */
final class InventoryReport implements Report
{
    public function key(): string
    {
        return 'inventory';
    }

    public function title(): string
    {
        return 'المخزون وقيمته';
    }

    public function description(): string
    {
        return 'الموجود دلوقتي وقيمته بسعر التكلفة وسعر البيع، والناقص.';
    }

    public function group(): string
    {
        return 'stock';
    }

    public function permission(): ?string
    {
        return 'inventory.view';
    }

    public function usesDates(): bool
    {
        return false;
    }

    public function usesBranches(): bool
    {
        return true;
    }

    public function options(): array
    {
        return [['key' => 'group', 'label' => 'حسب', 'choices' => [
            ['value' => 'category', 'label' => 'التصنيف'],
            ['value' => 'variant', 'label' => 'الصنف'],
        ]]];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $byVariant = $query->option('group', 'category') === 'variant';

        $base = DB::table('stock_levels')
            ->join('product_variants', 'product_variants.id', '=', 'stock_levels.variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->join('categories', 'categories.id', '=', 'products.category_id')
            ->where('stock_levels.tenant_id', $query->tenantId)
            ->whereIn('stock_levels.branch_id', $query->branchIds());

        $sums = 'sum(greatest(stock_levels.qty, 0)) as qty,
            sum(greatest(stock_levels.qty, 0) * stock_levels.avg_cost) as cost_value,
            sum(greatest(stock_levels.qty, 0) * product_variants.price_retail) as retail_value';

        $rows = ($byVariant
            ? $base->groupBy('product_variants.id', 'products.name', 'product_variants.name', 'categories.name', 'product_variants.barcode', 'product_variants.min_stock')
                ->selectRaw("trim(products.name || ' ' || coalesce(product_variants.name, '')) as name, categories.name as category, product_variants.barcode as barcode,
                    sum(stock_levels.qty) as on_hand, product_variants.min_stock as min_stock, {$sums}")
                ->orderBy('categories.name')->orderBy('name')
            : $base->groupBy('categories.id', 'categories.name')
                ->selectRaw("categories.name as name, count(distinct product_variants.id) as items,
                    count(distinct product_variants.id) filter (where stock_levels.qty <= 0) as out_count, {$sums}")
                ->orderByDesc('cost_value'))
            ->limit(5000)
            ->get();

        $totals = ['qty' => 0, 'cost_value' => 0, 'retail_value' => 0, 'items' => 0, 'out_count' => 0];
        $out = [];
        foreach ($rows as $r) {
            $row = ['name' => (string) $r->name];
            if ($byVariant) {
                $row += ['category' => (string) $r->category, 'barcode' => $r->barcode, 'qty' => (int) $r->on_hand];
                $row['status'] = (int) $r->on_hand <= 0 ? 'خلصان' : ($r->min_stock !== null && (int) $r->on_hand <= (int) $r->min_stock ? 'قرّب يخلص' : '');
            } else {
                $row += ['items' => (int) $r->items, 'out_count' => (int) $r->out_count, 'qty' => (int) $r->qty];
            }
            if ($query->withCost) {
                $row['cost_value'] = (int) $r->cost_value;
            }
            $row['retail_value'] = (int) $r->retail_value;
            foreach ($totals as $k => $_) {
                $totals[$k] += is_int($row[$k] ?? null) ? $row[$k] : 0;
            }
            $out[] = $row;
        }

        $columns = $byVariant
            ? [new Column('name', 'الصنف'), new Column('category', 'التصنيف'), new Column('barcode', 'الباركود'), new Column('qty', 'الكمية', 'int'), new Column('status', 'الحالة')]
            : [new Column('name', 'التصنيف'), new Column('items', 'أصناف', 'int'), new Column('out_count', 'خلصان', 'int'), new Column('qty', 'القطع', 'int')];
        if ($query->withCost) {
            $columns[] = new Column('cost_value', 'القيمة بالتكلفة', 'money');
        }
        $columns[] = new Column('retail_value', 'القيمة بسعر البيع', 'money');

        $summary = [['label' => 'القطع في المخزن', 'value' => $totals['qty'], 'type' => 'int']];
        if ($query->withCost) {
            $summary[] = ['label' => 'القيمة بالتكلفة', 'value' => $totals['cost_value'], 'type' => 'money'];
        }
        $summary[] = ['label' => 'القيمة بسعر البيع', 'value' => $totals['retail_value'], 'type' => 'money'];
        if ($query->withCost && $totals['retail_value'] > 0) {
            $summary[] = ['label' => 'الربح المتوقع', 'value' => $totals['retail_value'] - $totals['cost_value'], 'type' => 'money', 'hint' => 'لو اتباع كله بسعر القطاعي'];
        }

        return new ReportResult(
            columns: $columns,
            rows: $out,
            summary: $summary,
            totals: ['name' => 'الإجمالي', ...array_intersect_key($totals, array_flip(array_map(fn (Column $c) => $c->key, $columns)))],
            chart: $byVariant || $out === [] ? null : ['kind' => 'bars', 'label' => $query->withCost ? 'القيمة بالتكلفة' : 'القيمة بسعر البيع', 'type' => 'money', 'points' => array_map(
                fn ($r) => ['label' => $r['name'], 'value' => $r[$query->withCost ? 'cost_value' : 'retail_value']],
                array_slice($out, 0, 12),
            )],
            notes: ['الرصيد بالسالب (اتباع قبل ما يتسجل وارده) مش بيدخل في القيمة.'],
        );
    }
}
