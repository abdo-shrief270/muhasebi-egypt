<?php

declare(strict_types=1);

namespace App\Modules\Reports\Definitions;

use App\Modules\Reports\Support\Column;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportResult;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * «المتجر الأونلاين»: the orders customers placed in the period — how many became invoices,
 * how many were cancelled, what they sold and the delivery fees — by day, delivery zone or item.
 */
final class OnlineStoreReport implements Report
{
    public function key(): string
    {
        return 'online_store';
    }

    public function title(): string
    {
        return 'المتجر الأونلاين';
    }

    public function description(): string
    {
        return 'الطلبات اللي جت من المتجر، كام منها اتحول لفاتورة، المبيعات ومصاريف التوصيل، وأكتر الأصناف والمناطق.';
    }

    public function group(): string
    {
        return 'sales';
    }

    public function permission(): ?string
    {
        return 'online_store.orders';
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
            ['value' => 'day', 'label' => 'اليوم'],
            ['value' => 'zone', 'label' => 'منطقة التوصيل'],
            ['value' => 'item', 'label' => 'الصنف'],
        ]]];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $orders = fn (): Builder => $query->inPeriod(DB::table('online_orders as o'), 'o.created_at')
            ->where('o.tenant_id', $query->tenantId)
            ->whereIn('o.branch_id', $query->branchIds());
        $group = $query->option('group', 'day');

        // Headline figures for the whole period, whatever the grouping.
        $all = $orders()->selectRaw("count(*) as orders,
            count(*) filter (where o.status = 'delivered') as delivered,
            count(*) filter (where o.status = 'cancelled') as cancelled,
            coalesce(sum(o.subtotal) filter (where o.status = 'delivered'), 0) as sales,
            coalesce(sum(o.delivery_fee) filter (where o.status = 'delivered'), 0) as fees")->first();
        $summary = [
            ['label' => 'الطلبات', 'value' => (int) $all->orders, 'type' => 'int'],
            ['label' => 'اتحولت لفواتير', 'value' => (int) $all->delivered, 'type' => 'int', 'hint' => $this->rate((int) $all->delivered, (int) $all->orders).' من الطلبات'],
            ['label' => 'اتلغت', 'value' => (int) $all->cancelled, 'type' => 'int'],
            ['label' => 'مبيعات المتجر', 'value' => (int) $all->sales, 'type' => 'money', 'hint' => 'الأصناف في الطلبات اللي اتسلّمت'],
            ['label' => 'مصاريف التوصيل', 'value' => (int) $all->fees, 'type' => 'money'],
        ];

        if ($group === 'item') {
            return $this->byItem($query, $orders, $summary);
        }

        $measures = "count(*) as orders,
            count(*) filter (where o.status = 'delivered') as delivered,
            count(*) filter (where o.status = 'cancelled') as cancelled,
            coalesce(sum(o.subtotal) filter (where o.status = 'delivered'), 0) as sales,
            coalesce(sum(o.delivery_fee) filter (where o.status = 'delivered'), 0) as fees";
        $day = ReportQuery::localDay('o.created_at');
        $rows = $group === 'zone'
            ? $orders()->groupByRaw("coalesce(o.zone_name, '')")->selectRaw("coalesce(o.zone_name, '') as k, {$measures}")->orderByDesc('orders')->get()
            : $orders()->groupByRaw($day)->selectRaw("{$day} as k, {$measures}")->orderBy('k')->get();

        $out = [];
        foreach ($rows as $r) {
            $out[] = [
                'name' => $group === 'zone' && $r->k === '' ? 'استلام من المحل' : (string) $r->k,
                'orders' => (int) $r->orders,
                'delivered' => (int) $r->delivered,
                'rate' => $this->rate((int) $r->delivered, (int) $r->orders),
                'cancelled' => (int) $r->cancelled,
                'sales' => (int) $r->sales,
                'fees' => (int) $r->fees,
            ];
        }

        return new ReportResult(
            columns: [
                new Column('name', $group === 'zone' ? 'المنطقة' : 'اليوم', $group === 'day' ? 'date' : 'text'),
                new Column('orders', 'الطلبات', 'int'),
                new Column('delivered', 'اتسلّمت', 'int'),
                new Column('rate', 'نسبة التحويل'),
                new Column('cancelled', 'اتلغت', 'int'),
                new Column('sales', 'المبيعات', 'money'),
                new Column('fees', 'التوصيل', 'money'),
            ],
            rows: $out,
            summary: $summary,
            totals: [
                'name' => 'الإجمالي', 'orders' => (int) $all->orders, 'delivered' => (int) $all->delivered,
                'rate' => $this->rate((int) $all->delivered, (int) $all->orders), 'cancelled' => (int) $all->cancelled,
                'sales' => (int) $all->sales, 'fees' => (int) $all->fees,
            ],
            chart: $out === [] ? null : ($group === 'day'
                ? ['kind' => 'daily', 'label' => 'الطلبات', 'type' => 'int', 'points' => array_map(fn ($r) => ['label' => $r['name'], 'date' => $r['name'], 'value' => $r['orders']], $out)]
                : ['kind' => 'bars', 'label' => 'الطلبات', 'type' => 'int', 'points' => array_map(fn ($r) => ['label' => $r['name'], 'value' => $r['orders']], $out)]),
            notes: [
                'الطلب بيتحسب في يوم ما الزبون عمله. «اتسلّمت» = اتعملها فاتورة من الكاشير، والمبيعات دي جوّه تقرير المبيعات كمان (مش زيادة عليه).',
            ],
        );
    }

    /**
     * @param  callable(): Builder  $orders
     * @param  list<array<string, mixed>>  $summary
     */
    private function byItem(ReportQuery $query, callable $orders, array $summary): ReportResult
    {
        $rows = $orders()->join('online_order_items as i', 'i.order_id', '=', 'o.id')
            ->groupBy('i.variant_id', 'i.name')
            ->selectRaw("i.name as name, sum(i.qty) as ordered,
                coalesce(sum(i.qty) filter (where o.status = 'delivered'), 0) as delivered,
                coalesce(sum(i.line_total) filter (where o.status = 'delivered'), 0) as sales,
                count(distinct o.id) as orders")
            ->orderByDesc('ordered')
            ->limit(200)
            ->get();

        $out = array_map(fn ($r) => [
            'name' => (string) $r->name,
            'orders' => (int) $r->orders,
            'ordered' => (int) $r->ordered,
            'delivered' => (int) $r->delivered,
            'sales' => (int) $r->sales,
        ], $rows->all());

        return new ReportResult(
            columns: [
                new Column('name', 'الصنف'),
                new Column('orders', 'في طلبات', 'int'),
                new Column('ordered', 'الكمية المطلوبة', 'int'),
                new Column('delivered', 'اتسلّم منها', 'int'),
                new Column('sales', 'المبيعات', 'money'),
            ],
            rows: $out,
            summary: $summary,
            totals: [
                'name' => 'الإجمالي',
                'orders' => null,
                'ordered' => array_sum(array_column($out, 'ordered')),
                'delivered' => array_sum(array_column($out, 'delivered')),
                'sales' => array_sum(array_column($out, 'sales')),
            ],
            chart: $out === [] ? null : ['kind' => 'bars', 'label' => 'الكمية المطلوبة', 'type' => 'int', 'points' => array_map(fn ($r) => ['label' => $r['name'], 'value' => $r['ordered']], array_slice($out, 0, 15))],
            notes: ['أكتر 200 صنف اتطلبوا في الفترة.'],
        );
    }

    private function rate(int $part, int $whole): string
    {
        return $whole > 0 ? round($part * 100 / $whole).'%' : '—';
    }
}
