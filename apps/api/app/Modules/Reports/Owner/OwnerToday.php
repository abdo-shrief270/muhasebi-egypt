<?php

declare(strict_types=1);

namespace App\Modules\Reports\Owner;

use App\Modules\Reports\Support\ReportQuery;
use App\Support\Modules\ModuleAccess;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * The owner's «النهارده»: today so far against the same time yesterday, sales by hour, open
 * drawers, expenses, returns, top items, low stock, and the repairs / installments waiting.
 * Read-only SQL over the other modules' tables, like every report.
 */
final class OwnerToday
{
    public function __construct(private readonly ModuleAccess $modules) {}

    /**
     * @param  list<string>  $branchIds
     * @return array<string, mixed>
     */
    public function for(string $tenantId, array $branchIds, bool $withProfit): array
    {
        $now = CarbonImmutable::now(ReportQuery::TZ);
        $start = $now->startOfDay();
        $yesterdayStart = $start->subDay();
        $lastWeekStart = $start->subWeek();

        $sales = fn (CarbonImmutable $from, CarbonImmutable $to): Builder => DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->whereIn('branch_id', $branchIds)
            ->whereBetween('completed_at', [$from->utc(), $to->utc()]);

        $today = $sales($start, $now)->selectRaw('count(*) as invoices, coalesce(sum(total - refunded), 0) as net, coalesce(sum(total - refunded - cost_total + refunded_cost), 0) as profit, coalesce(sum(discount), 0) as discount')->first();
        $sameTimeYesterday = (int) $sales($yesterdayStart, $now->subDay())->sum(DB::raw('total - refunded'));
        $lastWeekDay = (int) $sales($lastWeekStart, $lastWeekStart->endOfDay())->sum(DB::raw('total - refunded'));

        $hour = 'extract(hour from (completed_at at time zone \'UTC\' at time zone \''.ReportQuery::TZ.'\'))::int';
        $byHour = fn (CarbonImmutable $from, CarbonImmutable $to) => $sales($from, $to)->groupByRaw($hour)->selectRaw("{$hour} as h, sum(total - refunded) as net")->pluck('net', 'h');
        $hoursToday = $byHour($start, $now);
        $hoursYesterday = $byHour($yesterdayStart, $yesterdayStart->endOfDay());

        $returns = DB::table('sale_returns')->where('tenant_id', $tenantId)->whereIn('branch_id', $branchIds)
            ->whereBetween('created_at', [$start->utc(), $now->utc()])
            ->selectRaw('count(*) as count, coalesce(sum(total), 0) as amount')->first();
        $expenses = -(int) DB::table('cash_movements')->where('tenant_id', $tenantId)->whereIn('branch_id', $branchIds)
            ->where('type', 'expense')->whereBetween('created_at', [$start->utc(), $now->utc()])->sum('amount');

        $cashIn = DB::table('cash_movements')->selectRaw('shift_id, sum(amount) as cash')->where('method', 'cash')->groupBy('shift_id');
        $openShifts = DB::table('cash_shifts as s')
            ->leftJoin('branches as b', 'b.id', '=', 's.branch_id')
            ->leftJoinSub($cashIn, 'm', 'm.shift_id', '=', 's.id')
            ->where('s.tenant_id', $tenantId)->whereIn('s.branch_id', $branchIds)->whereNull('s.closed_at')
            ->orderBy('s.opened_at')
            ->get(['s.id', 's.number', 's.user_name', 'b.name as branch', 's.opened_at', DB::raw('s.opening_cash + coalesce(m.cash, 0) as expected_cash')])
            ->map(fn ($s) => [
                'id' => $s->id,
                'reference' => 'SH-'.str_pad((string) $s->number, 5, '0', STR_PAD_LEFT),
                'user_name' => $s->user_name,
                'branch' => $s->branch,
                'opened_at' => CarbonImmutable::parse($s->opened_at)->toIso8601String(),
                'expected_cash' => (int) $s->expected_cash,
            ])->all();

        $top = DB::table('sale_items as i')->join('sales as s', 's.id', '=', 'i.sale_id')
            ->where('s.tenant_id', $tenantId)->whereIn('s.branch_id', $branchIds)
            ->whereBetween('s.completed_at', [$start->utc(), $now->utc()])
            ->groupBy('i.variant_id', 'i.name')
            ->selectRaw('i.name, sum(i.qty - i.returned_qty) as qty, sum(i.line_total) as amount')
            ->orderByDesc('amount')->limit(5)->get()
            ->map(fn ($r) => ['name' => (string) $r->name, 'qty' => (int) $r->qty, 'amount' => (int) $r->amount])->all();

        $lowStock = (int) DB::table('stock_levels as l')->join('product_variants as v', 'v.id', '=', 'l.variant_id')
            ->where('l.tenant_id', $tenantId)->whereIn('l.branch_id', $branchIds)
            ->where('v.is_active', true)->where('v.min_stock', '>', 0)->whereColumn('l.qty', '<=', 'v.min_stock')->count();

        return [
            'as_of' => $now->toIso8601String(),
            'sales' => [
                'net' => (int) $today->net,
                'invoices' => (int) $today->invoices,
                'average' => $today->invoices > 0 ? intdiv((int) $today->net, (int) $today->invoices) : 0,
                'profit' => $withProfit ? (int) $today->profit : null,
                'discount' => (int) $today->discount,
                'same_time_yesterday' => $sameTimeYesterday,
                'last_week_day' => $lastWeekDay,
            ],
            'by_hour' => array_map(fn (int $h) => ['hour' => $h, 'today' => $h <= $now->hour ? (int) ($hoursToday[$h] ?? 0) : null, 'yesterday' => (int) ($hoursYesterday[$h] ?? 0)], range(0, 23)),
            'returns' => ['count' => (int) $returns->count, 'amount' => (int) $returns->amount],
            'expenses' => $expenses,
            'open_shifts' => $openShifts,
            'top_items' => $top,
            'low_stock' => $lowStock,
            'repairs' => $this->modules->enabled('repairs', $tenantId) ? $this->repairs($tenantId, $branchIds, $start, $now) : null,
            'installments' => $this->modules->enabled('installments', $tenantId) ? $this->installments($tenantId, $branchIds, $now) : null,
        ];
    }

    /**
     * @param  list<string>  $branchIds
     * @return array{ready: int, in_progress: int, received_today: int, delivered_today: int}
     */
    private function repairs(string $tenantId, array $branchIds, CarbonImmutable $start, CarbonImmutable $now): array
    {
        $tickets = fn () => DB::table('repair_tickets')->where('tenant_id', $tenantId)->whereIn('branch_id', $branchIds);

        return [
            'ready' => $tickets()->where('status', 'ready')->whereNull('delivered_at')->count(),
            'in_progress' => $tickets()->whereIn('status', ['received', 'diagnosing', 'awaiting_approval', 'repairing', 'awaiting_part'])->count(),
            'received_today' => $tickets()->whereBetween('received_at', [$start->utc(), $now->utc()])->count(),
            'delivered_today' => $tickets()->whereBetween('delivered_at', [$start->utc(), $now->utc()])->count(),
        ];
    }

    /**
     * @param  list<string>  $branchIds
     * @return array{late_amount: int, due_today: int}
     */
    private function installments(string $tenantId, array $branchIds, CarbonImmutable $now): array
    {
        $open = fn () => DB::table('installment_items as i')->join('installment_plans as p', 'p.id', '=', 'i.plan_id')
            ->where('p.tenant_id', $tenantId)->where('p.status', 'active')->whereIn('p.branch_id', $branchIds)
            ->whereColumn('i.paid', '<', 'i.amount');

        return [
            'late_amount' => (int) $open()->where('i.due_on', '<', $now->toDateString())->sum(DB::raw('i.amount - i.paid')),
            'due_today' => (int) $open()->where('i.due_on', $now->toDateString())->sum(DB::raw('i.amount - i.paid')),
        ];
    }
}
