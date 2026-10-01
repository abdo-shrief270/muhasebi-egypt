<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Services\Contracts\ServiceProfits;
use App\Support\Modules\FeatureAccess;
use App\Support\Modules\ModuleAccess;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Numbers for the owner's dashboard, across all branches, by day in Cairo time.
 * Revenue is net of refunds; profit (only with reports.profit) is revenue minus the FIFO cost of
 * what stayed sold.
 */
final class SalesStatsController
{
    private const TZ = 'Africa/Cairo';

    public function __invoke(Request $request, CurrentTenant $tenant, ModuleAccess $modules, FeatureAccess $features): JsonResponse
    {
        $days = min(90, max(7, $request->integer('days', 14)));
        // The owner's «المكسب في الرئيسية» switch hides profit from the home page for everyone (reports keep it).
        $withProfit = (bool) $request->user()?->can('reports.profit') && $features->enabled('reports.home_profit');
        $tenantId = $tenant->idOrFail();

        $today = CarbonImmutable::now(self::TZ)->startOfDay();
        $from = $today->subDays($days - 1);
        $fromUtc = $from->utc();
        $localDay = "(completed_at at time zone 'UTC' at time zone '".self::TZ."')::date";

        $rows = DB::table('sales')
            ->where('tenant_id', $tenantId)
            ->where('completed_at', '>=', $fromUtc)
            ->groupByRaw($localDay)
            ->selectRaw("{$localDay} as day, count(*) as sales, sum(total - refunded) as revenue, sum((total - refunded) - (cost_total - refunded_cost)) as profit")
            ->get()
            ->keyBy(fn ($r) => (string) $r->day);

        $series = [];
        for ($d = $from; $d <= $today; $d = $d->addDay()) {
            $row = $rows->get($d->toDateString());
            $series[] = [
                'date' => $d->toDateString(),
                'sales' => (int) ($row->sales ?? 0),
                'revenue' => (int) ($row->revenue ?? 0),
                'profit' => $withProfit ? (int) ($row->profit ?? 0) : null,
            ];
        }

        $todayRow = end($series);
        $yesterday = $series[count($series) - 2] ?? null;

        $top = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.completed_at', '>=', $fromUtc)
            ->whereColumn('sale_items.returned_qty', '<', 'sale_items.qty')
            ->groupBy('sale_items.variant_id')
            ->selectRaw('max(sale_items.name) as name, sum(sale_items.qty - sale_items.returned_qty) as qty, sum(sale_items.line_total * (sale_items.qty - sale_items.returned_qty) / sale_items.qty) as revenue')
            ->orderByDesc('revenue')
            ->limit(5)
            ->get()
            ->map(fn ($r) => ['name' => $r->name, 'qty' => (int) $r->qty, 'revenue' => (int) $r->revenue])
            ->all();

        // Change is handed back in cash, so it comes off cash taken.
        $payments = DB::table('sale_payments')
            ->join('sales', 'sales.id', '=', 'sale_payments.sale_id')
            ->where('sales.tenant_id', $tenantId)
            ->where('sales.completed_at', '>=', $fromUtc)
            ->groupBy('sale_payments.method')
            ->selectRaw('sale_payments.method, sum(sale_payments.amount) as amount')
            ->pluck('amount', 'method');
        $change = (int) DB::table('sales')->where('tenant_id', $tenantId)->where('completed_at', '>=', $fromUtc)->sum('change');

        $byMethod = [];
        foreach (PaymentMethod::cases() as $method) {
            $amount = (int) ($payments[$method->value] ?? 0) - ($method === PaymentMethod::Cash ? $change : 0);
            if ($amount > 0) {
                $byMethod[] = ['method' => $method->value, 'label' => $method->label(), 'amount' => $amount];
            }
        }

        // Wallet / airtime profit, apart from goods: only for shops using the module, and only with reports.profit.
        $services = null;
        if ($withProfit && $modules->enabled('services', $tenantId)) {
            $daily = app(ServiceProfits::class)->daily($tenantId, $fromUtc);
            $services = [
                'today' => $daily[$today->toDateString()]['profit'] ?? 0,
                'today_operations' => $daily[$today->toDateString()]['operations'] ?? 0,
                'period' => array_sum(array_column($daily, 'profit')),
            ];
        }

        return response()->json(['data' => [
            'days' => $days,
            'today' => [
                'sales' => $todayRow['sales'],
                'revenue' => $todayRow['revenue'],
                'profit' => $todayRow['profit'],
                'average' => $todayRow['sales'] > 0 ? intdiv($todayRow['revenue'], $todayRow['sales']) : 0,
                'revenue_yesterday' => $yesterday['revenue'] ?? 0,
            ],
            'period' => [
                'sales' => array_sum(array_column($series, 'sales')),
                'revenue' => array_sum(array_column($series, 'revenue')),
                'profit' => $withProfit ? array_sum(array_column($series, 'profit')) : null,
            ],
            'series' => $series,
            'top_items' => $top,
            'payments' => $byMethod,
            'services' => $services,
        ]]);
    }
}
