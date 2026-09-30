<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Illuminate\Support\Facades\DB;

/**
 * Returns not settled yet (still in the bin, or on a note the source hasn't answered), by source
 * and by how long they've been waiting.
 */
final class OpenSupplierReturnsReport implements Report
{
    public function key(): string
    {
        return 'supplier_returns_open';
    }

    public function title(): string
    {
        return 'مرتجعات لسه متسوّتش';
    }

    public function description(): string
    {
        return 'قيمة المرتجعات اللي لسه عندك أو عند المورد ومتسوّتش، لكل مورد وحسب عمرها.';
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
        return false;
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
        $age = 'extract(day from now() - created_at)';
        $rows = DB::table('supplier_return_items')
            ->where('tenant_id', $query->tenantId)
            ->whereIn('branch_id', $query->branchIds())
            ->whereIn('status', ['in_bin', 'on_note'])
            ->groupBy('source_type', 'source_id', 'source_name')
            ->selectRaw("source_type, source_id, source_name,
                sum(qty) filter (where status = 'in_bin') as in_bin,
                sum(qty) filter (where status = 'on_note') as on_note,
                sum(qty) as units,
                coalesce(sum(qty * unit_cost) filter (where {$age} <= 7), 0) as week,
                coalesce(sum(qty * unit_cost) filter (where {$age} > 7 and {$age} <= 30), 0) as month,
                coalesce(sum(qty * unit_cost) filter (where {$age} > 30), 0) as older,
                sum(qty * unit_cost) as value,
                min(created_at) as oldest")
            ->orderByDesc('value')
            ->get();

        $keys = ['in_bin', 'on_note', 'units', 'week', 'month', 'older', 'value'];
        $totals = array_fill_keys($keys, 0);
        $out = [];
        foreach ($rows as $r) {
            $row = [
                'source' => $r->source_type === null ? 'مصدر مش معروف' : (string) $r->source_name,
                'type' => match ($r->source_type) {
                    'supplier' => 'مورد',
                    'shop' => 'محل شريك',
                    default => '—',
                },
                'oldest' => substr((string) $r->oldest, 0, 10),
            ];
            foreach ($keys as $k) {
                $row[$k] = (int) ($r->{$k} ?? 0);
                $totals[$k] += $row[$k];
            }
            $out[] = $row;
        }

        $money = ['week', 'month', 'older', 'value'];
        if (! $query->withCost) {
            foreach ($out as $i => $row) {
                foreach ($money as $k) {
                    unset($out[$i][$k]);
                }
            }
        }

        $columns = [
            new Column('source', 'المصدر'),
            new Column('type', 'النوع'),
            new Column('in_bin', 'في السلة', 'int'),
            new Column('on_note', 'على إذن مرتجع', 'int'),
            new Column('units', 'القطع', 'int'),
        ];
        if ($query->withCost) {
            array_push(
                $columns,
                new Column('week', 'أقل من أسبوع', 'money'),
                new Column('month', 'من أسبوع لشهر', 'money'),
                new Column('older', 'أكتر من شهر', 'money'),
                new Column('value', 'القيمة', 'money'),
            );
        }
        $columns[] = new Column('oldest', 'أقدم قطعة', 'date');

        $summary = [['label' => 'قطع مستنية تتسوّى', 'value' => $totals['units'], 'type' => 'int']];
        if ($query->withCost) {
            $summary[] = ['label' => 'قيمتها', 'value' => $totals['value'], 'type' => 'money'];
            $summary[] = ['label' => 'منها أكتر من شهر', 'value' => $totals['older'], 'type' => 'money'];
        }
        $totalRow = ['source' => 'الإجمالي', 'type' => null, 'oldest' => null, ...$totals];
        if (! $query->withCost) {
            foreach ($money as $k) {
                unset($totalRow[$k]);
            }
        }

        return new ReportResult(
            columns: $columns,
            rows: $out,
            summary: $summary,
            totals: $totalRow,
            notes: ['القيمة بالتكلفة. العمر من يوم ما القطعة دخلت سلة المرتجعات.'],
        );
    }
}
