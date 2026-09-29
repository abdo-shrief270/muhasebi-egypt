<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Labels;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Illuminate\Support\Facades\DB;

/** How customers paid (cash net of change), what was refunded per method, and collections on credit. */
final class PaymentsReport implements Report
{
    public function key(): string
    {
        return 'payments';
    }

    public function title(): string
    {
        return 'طرق الدفع';
    }

    public function description(): string
    {
        return 'كاش وفيزا ومحافظ وآجل: المقبوض والمردود والصافي.';
    }

    public function group(): string
    {
        return 'money';
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
        $paid = $query->inPeriod(DB::table('sale_payments'), 'sales.completed_at')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.tenant_id', $query->tenantId)
            ->whereIn('sales.branch_id', $query->branchIds())
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method, count(distinct sales.id) as invoices, sum(sale_payments.amount) as amount')
            ->get()->keyBy('method');
        $change = (int) $query->inPeriod(DB::table('sales'), 'completed_at')
            ->where('tenant_id', $query->tenantId)->whereIn('branch_id', $query->branchIds())->sum('change');
        $refunds = $query->inPeriod(DB::table('sale_returns'), 'created_at')
            ->where('tenant_id', $query->tenantId)->whereIn('branch_id', $query->branchIds())
            ->groupBy('refund_method')->selectRaw('refund_method, sum(total) as amount')->pluck('amount', 'refund_method');

        $out = [];
        $totals = ['invoices' => 0, 'received' => 0, 'refunded' => 0, 'net' => 0];
        foreach (['cash', 'card', 'wallet', 'instapay', 'credit'] as $method) {
            $received = (int) ($paid[$method]->amount ?? 0) - ($method === 'cash' ? $change : 0);
            $refunded = (int) ($refunds[$method] ?? 0);
            if ($received === 0 && $refunded === 0) {
                continue;
            }
            $row = ['method' => Labels::paymentMethod($method), 'invoices' => (int) ($paid[$method]->invoices ?? 0), 'received' => $received, 'refunded' => $refunded, 'net' => $received - $refunded];
            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k];
            }
            $out[] = $row;
        }
        foreach ($out as &$row) {
            $row['share'] = $totals['net'] !== 0 ? round($row['net'] / $totals['net'] * 100, 1) : null;
        }
        unset($row);

        $collected = -(int) $query->inPeriod(DB::table('customer_transactions'), 'created_at')
            ->where('tenant_id', $query->tenantId)
            ->where(fn ($q) => $q->whereIn('branch_id', $query->branchIds())->orWhereNull('branch_id'))
            ->where('type', 'payment')
            ->sum('amount');

        return new ReportResult(
            columns: [
                new Column('method', 'الطريقة'),
                new Column('invoices', 'الفواتير', 'int'),
                new Column('received', 'المقبوض', 'money'),
                new Column('refunded', 'المردود', 'money'),
                new Column('net', 'الصافي', 'money'),
                new Column('share', 'النسبة', 'percent'),
            ],
            rows: $out,
            summary: [
                ['label' => 'صافي المقبوض', 'value' => $totals['net'], 'type' => 'money'],
                ['label' => 'اتحصّل من الآجل', 'value' => $collected, 'type' => 'money', 'hint' => 'تحصيلات العملاء في الفترة'],
            ],
            totals: ['method' => 'الإجمالي', ...$totals, 'share' => $totals['net'] !== 0 ? 100.0 : null],
            chart: $out === [] ? null : ['kind' => 'bars', 'label' => 'الصافي', 'type' => 'money', 'points' => array_map(fn ($r) => ['label' => $r['method'], 'value' => $r['net']], $out)],
            notes: ['الكاش من غير الباقي اللي رجع للعميل. «آجل» = اللي اتكتب على حسابات العملاء.'],
        );
    }
}
