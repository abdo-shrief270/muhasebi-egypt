<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Closed shifts and how their drawers counted: shortages and overages per cashier. */
final class ShiftsReport implements Report
{
    public function key(): string
    {
        return 'shifts';
    }

    public function title(): string
    {
        return 'الورديات والعجز';
    }

    public function description(): string
    {
        return 'كل وردية اتقفلت: المفروض في الدرج والفعلي والفرق.';
    }

    public function group(): string
    {
        return 'money';
    }

    public function permission(): ?string
    {
        return 'cash.manage';
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
        $rows = $query->inPeriod(DB::table('cash_shifts'), 'closed_at')
            ->where('tenant_id', $query->tenantId)
            ->whereIn('branch_id', $query->branchIds())
            ->orderByDesc('closed_at')
            ->get(['number', 'user_name', 'branch_id', 'opened_at', 'closed_at', 'expected', 'counted', 'cash_difference']);

        $short = 0;
        $over = 0;
        $out = [];
        foreach ($rows as $r) {
            $expected = json_decode((string) $r->expected, true) ?: [];
            $counted = json_decode((string) $r->counted, true) ?: [];
            $difference = (int) $r->cash_difference;
            $difference < 0 ? $short += $difference : $over += $difference;
            $out[] = [
                'reference' => 'SH-'.str_pad((string) $r->number, 5, '0', STR_PAD_LEFT),
                'user' => $r->user_name,
                'branch' => $query->branches[$r->branch_id] ?? '',
                'opened_at' => CarbonImmutable::parse($r->opened_at)->toIso8601String(),
                'closed_at' => CarbonImmutable::parse($r->closed_at)->toIso8601String(),
                'expected' => (int) ($expected['cash'] ?? 0),
                'counted' => (int) ($counted['cash'] ?? 0),
                'difference' => $difference,
            ];
        }

        return new ReportResult(
            columns: [
                new Column('reference', 'الوردية'),
                new Column('user', 'الكاشير'),
                new Column('branch', 'الفرع'),
                new Column('opened_at', 'فتحت', 'datetime'),
                new Column('closed_at', 'اتقفلت', 'datetime'),
                new Column('expected', 'الكاش المفروض', 'money'),
                new Column('counted', 'الكاش الفعلي', 'money'),
                new Column('difference', 'الفرق', 'money'),
            ],
            rows: $out,
            summary: [
                ['label' => 'ورديات اتقفلت', 'value' => count($out), 'type' => 'int'],
                ['label' => 'إجمالي العجز', 'value' => -$short, 'type' => 'money'],
                ['label' => 'إجمالي الزيادة', 'value' => $over, 'type' => 'money'],
            ],
            totals: ['reference' => 'الإجمالي', 'difference' => $short + $over],
            notes: ['الفرق بالسالب = عجز في الدرج.'],
        );
    }
}
