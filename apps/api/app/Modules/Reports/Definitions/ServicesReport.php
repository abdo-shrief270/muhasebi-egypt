<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Labels;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Wallet transfers and airtime top-ups, apart from goods: the counter operations of the period
 * (net of reversals, which count on the day they were made) by account, operation or day. The fee
 * is what the customer paid on top; the margin is the airtime's distributor discount; profit = both.
 */
final class ServicesReport implements Report
{
    private const TYPES = ['deposit' => 'إيداع', 'withdraw' => 'سحب', 'topup' => 'شحن رصيد'];

    private const KINDS = ['wallet' => 'محفظة', 'airtime' => 'رصيد شحن'];

    public function key(): string
    {
        return 'services';
    }

    public function title(): string
    {
        return 'الشحن والتحويلات';
    }

    public function description(): string
    {
        return 'عمولات المحافظ ومكسب شحن الرصيد، لوحدها بعيد عن مكسب البضاعة.';
    }

    public function group(): string
    {
        return 'money';
    }

    public function permission(): ?string
    {
        return 'services.manage';
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
            ['value' => 'account', 'label' => 'المحفظة / الخط'],
            ['value' => 'type', 'label' => 'نوع العملية'],
            ['value' => 'day', 'label' => 'اليوم'],
        ]]];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $base = fn (): Builder => $query->inPeriod(DB::table('service_transactions as t'), 't.created_at')
            ->where('t.tenant_id', $query->tenantId)
            ->whereIn('t.branch_id', $query->branchIds())
            ->whereIn('t.type', array_keys(self::TYPES));
        $measures = 'count(*) filter (where t.reverses_id is null) - count(*) filter (where t.reverses_id is not null) as operations,
            coalesce(sum(t.amount), 0) as volume, coalesce(sum(t.fee), 0) as fees, coalesce(sum(t.profit), 0) as profit';

        $group = $query->option('group', 'account');
        $day = ReportQuery::localDay('t.created_at');
        $rows = match ($group) {
            'type' => $base()->groupBy('t.type')->selectRaw("t.type as k, {$measures}")->orderByDesc('profit')->get(),
            'day' => $base()->groupByRaw($day)->selectRaw("{$day} as k, {$measures}")->orderBy('k')->get(),
            default => $base()->join('service_accounts as a', 'a.id', '=', 't.account_id')
                ->groupBy('a.id', 'a.name', 'a.kind')
                ->selectRaw("a.name as k, a.kind as kind, {$measures}")
                ->orderByDesc('profit')
                ->get(),
        };

        $totals = ['operations' => 0, 'volume' => 0, 'fees' => 0, 'margin' => 0, 'profit' => 0];
        $out = [];
        foreach ($rows as $r) {
            $row = [
                'name' => match ($group) {
                    'type' => self::TYPES[$r->k] ?? (string) $r->k,
                    default => (string) $r->k,
                },
                'operations' => (int) $r->operations,
                'volume' => (int) $r->volume,
                'fees' => (int) $r->fees,
            ];
            if ($group === 'account') {
                $row['kind'] = self::KINDS[$r->kind] ?? (string) $r->kind;
            }
            if ($query->withProfit) {
                $row['margin'] = (int) $r->profit - (int) $r->fees;
                $row['profit'] = (int) $r->profit;
            }
            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k] ?? 0;
            }
            $out[] = $row;
        }

        $columns = [new Column('name', match ($group) {
            'type' => 'العملية',
            'day' => 'اليوم',
            default => 'المحفظة / الخط',
        }, $group === 'day' ? 'date' : 'text')];
        if ($group === 'account') {
            $columns[] = new Column('kind', 'النوع');
        }
        array_push($columns, new Column('operations', 'العمليات', 'int'), new Column('volume', 'المبالغ', 'money'), new Column('fees', 'العمولات', 'money'));
        if ($query->withProfit) {
            array_push($columns, new Column('margin', 'هامش الرصيد', 'money'), new Column('profit', 'المكسب', 'money'));
        }

        $summary = [
            ['label' => 'العمليات', 'value' => $totals['operations'], 'type' => 'int'],
            ['label' => 'المبالغ اللي عدّت', 'value' => $totals['volume'], 'type' => 'money'],
            ['label' => 'العمولات', 'value' => $totals['fees'], 'type' => 'money'],
        ];
        if ($query->withProfit) {
            $summary[] = ['label' => 'مكسب الخدمات', 'value' => $totals['profit'], 'type' => 'money', 'hint' => 'العمولات + هامش الرصيد'];
        }
        $totalsRow = ['name' => 'الإجمالي', ...$totals];
        if (! $query->withProfit) {
            unset($totalsRow['margin'], $totalsRow['profit']);
        }
        $measure = $query->withProfit ? 'profit' : 'fees';

        return new ReportResult(
            columns: $columns,
            rows: $out,
            summary: $summary,
            totals: $totalsRow,
            chart: $out === [] ? null : ($group === 'day'
                ? ['kind' => 'daily', 'label' => $query->withProfit ? 'مكسب الخدمات' : 'العمولات', 'type' => 'money', 'points' => array_map(fn ($r) => ['label' => $r['name'], 'date' => $r['name'], 'value' => $r[$measure]], $out)]
                : ['kind' => 'bars', 'label' => $query->withProfit ? 'المكسب' : 'العمولات', 'type' => 'money', 'points' => array_map(fn ($r) => ['label' => $r['name'], 'value' => $r[$measure]], $out)]),
            notes: array_values(array_filter([
                'إيداع وسحب المحافظ وشحن الرصيد اللي اتعمل في الفترة. العملية اللي اتلغت بتتخصم في يوم إلغائها.',
                $query->withProfit ? 'هامش الرصيد = الفرق بين قيمة الرصيد اللي اتشحن وتكلفته من الموزع. مش داخل في مكسب البضاعة.' : null,
                $query->withProfit && $totals['volume'] > 0 ? 'المكسب '.Labels::margin($totals['profit'], $totals['volume']).'% من المبالغ.' : null,
            ])),
        );
    }
}
