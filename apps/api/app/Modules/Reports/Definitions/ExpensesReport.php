<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Cash\Contracts\ExpenseCategory;
use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Expenses paid from the drawers, per category or one line each. */
final class ExpensesReport implements Report
{
    public function key(): string
    {
        return 'expenses';
    }

    public function title(): string
    {
        return 'المصروفات';
    }

    public function description(): string
    {
        return 'المصروفات حسب النوع، أو كل مصروف لوحده.';
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
        return [['key' => 'group', 'label' => 'العرض', 'choices' => [
            ['value' => 'category', 'label' => 'حسب النوع'],
            ['value' => 'list', 'label' => 'كل مصروف'],
        ]]];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $base = $query->inPeriod(DB::table('cash_movements'), 'created_at')
            ->where('tenant_id', $query->tenantId)
            ->whereIn('branch_id', $query->branchIds())
            ->where('type', 'expense');
        $label = fn (?string $c) => ExpenseCategory::tryFrom((string) $c)?->label() ?? 'أخرى';

        if ($query->option('group', 'category') === 'list') {
            $rows = $base->orderByDesc('seq')->limit(5000)->get(['created_at', 'category', 'amount', 'note', 'user_name', 'branch_id']);
            $out = $rows->map(fn ($r) => [
                'date' => CarbonImmutable::parse($r->created_at)->toIso8601String(),
                'category' => $label($r->category),
                'amount' => -(int) $r->amount,
                'note' => $r->note,
                'user' => $r->user_name,
                'branch' => $query->branches[$r->branch_id] ?? '',
            ])->all();
            $columns = [new Column('date', 'التاريخ', 'datetime'), new Column('category', 'النوع'), new Column('amount', 'المبلغ', 'money'), new Column('note', 'التفاصيل'), new Column('user', 'سجّله'), new Column('branch', 'الفرع')];
            $totals = ['date' => 'الإجمالي', 'amount' => array_sum(array_column($out, 'amount'))];
        } else {
            $rows = $base->groupBy('category')->selectRaw('category, count(*) as count, -sum(amount) as amount')->orderByDesc('amount')->get();
            $total = (int) $rows->sum('amount');
            $out = $rows->map(fn ($r) => [
                'category' => $label($r->category),
                'count' => (int) $r->count,
                'amount' => (int) $r->amount,
                'share' => $total > 0 ? round((int) $r->amount / $total * 100, 1) : null,
            ])->all();
            $columns = [new Column('category', 'النوع'), new Column('count', 'عدد', 'int'), new Column('amount', 'المبلغ', 'money'), new Column('share', 'النسبة', 'percent')];
            $totals = ['category' => 'الإجمالي', 'count' => (int) $rows->sum('count'), 'amount' => $total, 'share' => $total > 0 ? 100.0 : null];
        }

        $sum = (int) $totals['amount'];
        $days = max(1, (int) $query->from->diffInDays($query->to) + 1);

        return new ReportResult(
            columns: $columns,
            rows: array_values($out),
            summary: [
                ['label' => 'إجمالي المصروفات', 'value' => $sum, 'type' => 'money'],
                ['label' => 'في المتوسط يومياً', 'value' => intdiv($sum, $days), 'type' => 'money'],
            ],
            totals: $totals,
            chart: $query->option('group', 'category') === 'list' || $out === [] ? null : ['kind' => 'bars', 'label' => 'المصروفات', 'type' => 'money', 'points' => array_map(fn ($r) => ['label' => $r['category'], 'value' => $r['amount']], array_values($out))],
        );
    }
}
