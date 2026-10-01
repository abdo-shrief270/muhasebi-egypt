<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Labels;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use App\Support\Modules\FeatureAccess;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The repair desk: per technician (devices handed back in the period, the money and, with
 * reports.profit, profit and commissions), or what comes in (tickets received in the period)
 * by fault or by device model. A device counts as repaired when it reached «جاهز» before delivery.
 */
final class RepairsReport implements Report
{
    public function key(): string
    {
        return 'repairs';
    }

    /** The owner's «عمولة الفنيين» switch: off = no commission anywhere. */
    private static function commissions(ReportQuery $query): bool
    {
        return app(FeatureAccess::class)->enabled('repairs.commission', $query->tenantId);
    }

    public function title(): string
    {
        return 'الصيانة';
    }

    public function description(): string
    {
        return 'شغل الفنيين وعمولاتهم، وأكتر الأعطال والموديلات اللي بتدخل.';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function permission(): ?string
    {
        return 'repairs.view';
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
            ['value' => 'technician', 'label' => 'الفني'],
            ['value' => 'fault', 'label' => 'العطل'],
            ['value' => 'model', 'label' => 'الموديل'],
        ]]];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $delivered = fn (): Builder => $query->inPeriod(DB::table('repair_tickets'), 'delivered_at')
            ->where('tenant_id', $query->tenantId)
            ->whereIn('branch_id', $query->branchIds())
            ->where('status', 'delivered');
        $received = fn (): Builder => $query->inPeriod(DB::table('repair_tickets'), 'received_at')
            ->where('tenant_id', $query->tenantId)
            ->whereIn('branch_id', $query->branchIds());

        $totals = $delivered()->selectRaw('count(*) as tickets, count(*) filter (where ready_at is not null) as repaired,
            coalesce(sum(total), 0) as revenue, coalesce(sum(total - parts_cost - outsource_cost), 0) as profit, coalesce(sum(commission), 0) as commission,
            avg(extract(epoch from delivered_at - received_at)) / 86400 as days')->first();
        $summary = [
            ['label' => 'أجهزة دخلت', 'value' => $received()->count(), 'type' => 'int'],
            ['label' => 'اتسلّمت', 'value' => (int) $totals->tickets, 'type' => 'int', 'hint' => (int) $totals->tickets > 0 ? 'اتصلّح منها '.round($totals->repaired / $totals->tickets * 100).'%' : null],
            ['label' => 'إيراد الصيانة', 'value' => (int) $totals->revenue, 'type' => 'money'],
            ['label' => 'متوسط مدة الإصلاح', 'value' => self::duration($totals->days), 'type' => 'text'],
        ];
        if ($query->withProfit) {
            array_splice($summary, 3, 0, [
                ['label' => 'المكسب (بعد القطع)', 'value' => (int) $totals->profit, 'type' => 'money'],
                ...(self::commissions($query) ? [['label' => 'عمولات الفنيين', 'value' => (int) $totals->commission, 'type' => 'money']] : []),
            ]);
        }
        $summary = array_map(fn ($s) => array_filter($s, fn ($v) => $v !== null), $summary);

        return match ($query->option('group', 'technician')) {
            'fault' => $this->byFault($query, $received(), $summary),
            'model' => $this->byModel($received(), $summary),
            default => $this->byTechnician($query, $delivered(), $summary),
        };
    }

    /** From receiving to handing back: hours under a day, days otherwise. */
    private static function duration(mixed $days): string
    {
        if ($days === null) {
            return '—';
        }
        $days = (float) $days;

        return $days < 1 ? max(1, (int) round($days * 24)).' ساعة' : round($days, 1).' يوم';
    }

    /**
     * @param  list<array<string, mixed>>  $summary
     */
    private function byTechnician(ReportQuery $query, Builder $delivered, array $summary): ReportResult
    {
        $rows = $delivered->groupBy('technician_id')
            ->selectRaw('max(technician_name) as name, count(*) as tickets, count(*) filter (where ready_at is not null) as repaired,
                sum(labor) as labor, sum(parts_total) as parts, sum(total) as revenue, sum(total - parts_cost - outsource_cost) as profit, sum(commission) as commission,
                avg(extract(epoch from delivered_at - received_at)) / 86400 as days')
            ->orderByDesc('revenue')
            ->get();

        $totals = ['tickets' => 0, 'repaired' => 0, 'labor' => 0, 'parts' => 0, 'revenue' => 0, 'profit' => 0, 'commission' => 0];
        $out = [];
        foreach ($rows as $r) {
            $row = [
                'name' => $r->name ?? 'من غير فني',
                'tickets' => (int) $r->tickets,
                'repaired' => (int) $r->repaired,
                'labor' => (int) $r->labor,
                'parts' => (int) $r->parts,
                'revenue' => (int) $r->revenue,
                'days' => self::duration($r->days),
            ];
            if ($query->withProfit) {
                $row['profit'] = (int) $r->profit;
                $row['commission'] = (int) $r->commission;
                $row['margin'] = Labels::margin($row['profit'], $row['revenue']);
            }
            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k] ?? 0;
            }
            $out[] = $row;
        }

        $columns = [
            new Column('name', 'الفني'),
            new Column('tickets', 'اتسلّم', 'int'),
            new Column('repaired', 'اتصلّح', 'int'),
            new Column('labor', 'المصنعية', 'money'),
            new Column('parts', 'القطع', 'money'),
            new Column('revenue', 'الإجمالي', 'money'),
        ];
        if ($query->withProfit) {
            array_push($columns, new Column('profit', 'المكسب', 'money'), new Column('margin', 'الهامش', 'percent'));
            if (self::commissions($query)) {
                $columns[] = new Column('commission', 'العمولة', 'money');
            }
        }
        $columns[] = new Column('days', 'متوسط المدة', 'text');

        return new ReportResult(
            columns: $columns,
            rows: $out,
            summary: $summary,
            totals: ['name' => 'الإجمالي', ...$totals],
            chart: $out === [] ? null : ['kind' => 'bars', 'label' => 'الإجمالي لكل فني', 'type' => 'money', 'points' => array_map(fn ($r) => ['label' => $r['name'], 'value' => $r['revenue']], $out)],
            notes: ['الأجهزة اللي اتسلّمت في الفترة. العمولة بتتحسب وقت التسليم حسب قاعدة الفني، ومفيش عمولة على الرجوع في الضمان.'],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $summary
     */
    private function byFault(ReportQuery $query, Builder $received, array $summary): ReportResult
    {
        // The technician's diagnosis when there is one, else what the customer said.
        $faults = DB::query()
            ->fromSub($received->select(['id', 'diagnosed_faults', 'reported_faults']), 't')
            ->crossJoin(DB::raw('jsonb_array_elements(coalesce(t.diagnosed_faults, t.reported_faults)::jsonb) as f'))
            ->groupByRaw("f->>'category', f->>'name'")
            ->selectRaw("f->>'category' as category, f->>'name' as name, count(distinct t.id) as tickets")
            ->orderByDesc('tickets')
            ->limit(200)
            ->get();
        $total = (int) $faults->sum('tickets');
        $out = $faults->map(fn ($r) => [
            'name' => (string) $r->name,
            'category' => (string) $r->category,
            'tickets' => (int) $r->tickets,
            'share' => $total > 0 ? round($r->tickets / $total * 100, 1) : null,
        ])->all();

        return new ReportResult(
            columns: [new Column('name', 'العطل'), new Column('category', 'الجزء'), new Column('tickets', 'أجهزة', 'int'), new Column('share', 'النسبة', 'percent')],
            rows: $out,
            summary: $summary,
            chart: $out === [] ? null : ['kind' => 'bars', 'label' => 'أكتر الأعطال', 'type' => 'int', 'points' => array_map(fn ($r) => ['label' => $r['name'], 'value' => $r['tickets']], array_slice($out, 0, 10))],
            notes: ['الأجهزة اللي دخلت في الفترة: تشخيص الفني لو موجود، وإلا كلام العميل. الجهاز الواحد ممكن يبقى فيه أكتر من عطل.'],
        );
    }

    /**
     * @param  list<array<string, mixed>>  $summary
     */
    private function byModel(Builder $received, array $summary): ReportResult
    {
        $rows = $received->groupBy('device_name')
            ->selectRaw("device_name as name, count(*) as tickets, count(*) filter (where status = 'delivered' and ready_at is not null) as repaired,
                count(*) filter (where status <> 'delivered') as open, coalesce(sum(total) filter (where status = 'delivered'), 0) as revenue")
            ->orderByDesc('tickets')
            ->limit(200)
            ->get();
        $out = $rows->map(fn ($r) => ['name' => (string) $r->name, 'tickets' => (int) $r->tickets, 'repaired' => (int) $r->repaired, 'open' => (int) $r->open, 'revenue' => (int) $r->revenue])->all();

        return new ReportResult(
            columns: [new Column('name', 'الموديل'), new Column('tickets', 'دخل', 'int'), new Column('repaired', 'اتصلّح واتسلّم', 'int'), new Column('open', 'لسه عندك', 'int'), new Column('revenue', 'الإيراد', 'money')],
            rows: $out,
            summary: $summary,
            totals: ['name' => 'الإجمالي', 'tickets' => array_sum(array_column($out, 'tickets')), 'repaired' => array_sum(array_column($out, 'repaired')), 'open' => array_sum(array_column($out, 'open')), 'revenue' => array_sum(array_column($out, 'revenue'))],
            chart: $out === [] ? null : ['kind' => 'bars', 'label' => 'أكتر الموديلات', 'type' => 'int', 'points' => array_map(fn ($r) => ['label' => $r['name'], 'value' => $r['tickets']], array_slice($out, 0, 10))],
            notes: ['الأجهزة اللي دخلت في الفترة.'],
        );
    }
}
