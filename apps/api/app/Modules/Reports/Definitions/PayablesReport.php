<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Illuminate\Support\Facades\DB;

/** What the shop owes each supplier now, with purchases and payments in the period. */
final class PayablesReport implements Report
{
    public function key(): string
    {
        return 'payables';
    }

    public function title(): string
    {
        return 'حسابات الموردين';
    }

    public function description(): string
    {
        return 'اللي عليك لكل مورد، ومشترياتك ودفعاتك في الفترة.';
    }

    public function group(): string
    {
        return 'money';
    }

    public function permission(): ?string
    {
        return 'suppliers.view';
    }

    public function usesDates(): bool
    {
        return true;
    }

    public function usesBranches(): bool
    {
        return false;
    }

    public function options(): array
    {
        return [];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $period = $query->inPeriod(DB::table('supplier_transactions'), 'created_at')
            ->selectRaw("supplier_id, sum(amount) filter (where type = 'purchase') as purchases,
                -sum(amount) filter (where type = 'payment') as payments,
                -sum(amount) filter (where type = 'purchase_return') as returns")
            ->groupBy('supplier_id');
        $rows = DB::table('suppliers')
            ->leftJoinSub($period, 'p', 'p.supplier_id', '=', 'suppliers.id')
            ->where('suppliers.tenant_id', $query->tenantId)
            ->where(fn ($q) => $q->where('suppliers.balance', '!=', 0)->orWhereNotNull('p.supplier_id'))
            ->orderByDesc('suppliers.balance')
            ->select('suppliers.name', 'suppliers.phone', 'suppliers.balance', 'p.purchases', 'p.payments', 'p.returns')
            ->get();

        $totals = ['purchases' => 0, 'returns' => 0, 'payments' => 0, 'balance' => 0];
        $out = [];
        foreach ($rows as $r) {
            $row = [
                'name' => (string) $r->name,
                'phone' => $r->phone,
                'purchases' => (int) ($r->purchases ?? 0),
                'returns' => (int) ($r->returns ?? 0),
                'payments' => (int) ($r->payments ?? 0),
                'balance' => (int) $r->balance,
            ];
            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k];
            }
            $out[] = $row;
        }

        return new ReportResult(
            columns: [
                new Column('name', 'المورد'),
                new Column('phone', 'الموبايل'),
                new Column('purchases', 'مشتريات الفترة', 'money'),
                new Column('returns', 'مرتجعات الفترة', 'money'),
                new Column('payments', 'دفعات الفترة', 'money'),
                new Column('balance', 'الرصيد دلوقتي', 'money'),
            ],
            rows: $out,
            summary: [
                ['label' => 'عليك للموردين', 'value' => array_sum(array_map(fn ($r) => max(0, $r['balance']), $out)), 'type' => 'money'],
                ['label' => 'مشتريات الفترة', 'value' => $totals['purchases'], 'type' => 'money'],
                ['label' => 'دفعات الفترة', 'value' => $totals['payments'], 'type' => 'money'],
            ],
            totals: ['name' => 'الإجمالي', ...$totals],
            notes: ['الرصيد > 0 = عليك للمورد، < 0 = المورد عليه ليك.'],
        );
    }
}
