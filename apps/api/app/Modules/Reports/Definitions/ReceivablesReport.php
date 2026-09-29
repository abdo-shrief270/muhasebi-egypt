<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** Who owes the shop right now, and how long since they last paid. */
final class ReceivablesReport implements Report
{
    public function key(): string
    {
        return 'receivables';
    }

    public function title(): string
    {
        return 'الآجل عند العملاء';
    }

    public function description(): string
    {
        return 'مين عليه فلوس، وآخر مرة دفع إمتى.';
    }

    public function group(): string
    {
        return 'money';
    }

    public function permission(): ?string
    {
        return 'customers.view';
    }

    public function usesDates(): bool
    {
        return false;
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
        $last = DB::table('customer_transactions')
            ->selectRaw("customer_id, max(created_at) filter (where type = 'sale') as last_sale, max(created_at) filter (where type = 'payment') as last_payment")
            ->groupBy('customer_id');
        $rows = DB::table('customers')
            ->leftJoinSub($last, 'lt', 'lt.customer_id', '=', 'customers.id')
            ->where('customers.tenant_id', $query->tenantId)
            ->where('customers.balance', '>', 0)
            ->orderByDesc('customers.balance')
            ->select('customers.name', 'customers.phone', 'customers.balance', 'customers.credit_limit', 'lt.last_sale', 'lt.last_payment')
            ->get();

        $now = CarbonImmutable::now();
        $total = 0;
        $late = 0;
        $out = [];
        foreach ($rows as $r) {
            $since = $r->last_payment ?? $r->last_sale;
            $days = $since ? (int) CarbonImmutable::parse($since)->diffInDays($now) : null;
            $out[] = [
                'name' => (string) $r->name,
                'phone' => $r->phone ? preg_replace('/^\+20/', '0', (string) $r->phone) : null,
                'balance' => (int) $r->balance,
                'credit_limit' => $r->credit_limit !== null ? (int) $r->credit_limit : null,
                'last_sale' => $r->last_sale ? CarbonImmutable::parse($r->last_sale)->toIso8601String() : null,
                'last_payment' => $r->last_payment ? CarbonImmutable::parse($r->last_payment)->toIso8601String() : null,
                'days' => $days,
            ];
            $total += (int) $r->balance;
            if ($days !== null && $days > 30) {
                $late += (int) $r->balance;
            }
        }

        return new ReportResult(
            columns: [
                new Column('name', 'العميل'),
                new Column('phone', 'الموبايل'),
                new Column('balance', 'عليه', 'money'),
                new Column('credit_limit', 'حد الآجل', 'money'),
                new Column('last_sale', 'آخر فاتورة آجل', 'datetime'),
                new Column('last_payment', 'آخر تحصيل', 'datetime'),
                new Column('days', 'أيام من آخر حركة', 'int'),
            ],
            rows: $out,
            summary: [
                ['label' => 'إجمالي الآجل', 'value' => $total, 'type' => 'money'],
                ['label' => 'عملاء عليهم فلوس', 'value' => count($out), 'type' => 'int'],
                ['label' => 'متأخر أكتر من 30 يوم', 'value' => $late, 'type' => 'money'],
            ],
            totals: ['name' => 'الإجمالي', 'balance' => $total],
        );
    }
}
