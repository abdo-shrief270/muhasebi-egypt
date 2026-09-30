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
 * Used devices: bought and sold in the period, each one's profit (its sale price after the invoice
 * discount minus what was paid for it), and how long devices sit in stock — per device, model or grade.
 */
final class UsedDevicesReport implements Report
{
    private const DAYS = 'extract(epoch from (coalesce(sold_at, now()) - bought_at)) / 86400';

    public function key(): string
    {
        return 'used_devices';
    }

    public function title(): string
    {
        return 'المستعمل';
    }

    public function description(): string
    {
        return 'الأجهزة المستعملة اللي اشتريتها وبعتها، مكسب كل جهاز، وقعدت قد إيه في المخزن.';
    }

    public function group(): string
    {
        return 'stock';
    }

    public function permission(): ?string
    {
        return 'used_devices.manage';
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
            ['value' => 'device', 'label' => 'الجهاز'],
            ['value' => 'model', 'label' => 'الموديل'],
            ['value' => 'grade', 'label' => 'الفئة'],
        ]]];
    }

    public function run(ReportQuery $query): ReportResult
    {
        $base = fn (): Builder => DB::table('used_devices')->where('tenant_id', $query->tenantId)->whereIn('branch_id', $query->branchIds());
        $bought = fn (): Builder => $query->inPeriod($base(), 'bought_at');
        $sold = fn (): Builder => $query->inPeriod($base()->where('status', 'sold'), 'sold_at');

        $b = $bought()->selectRaw('count(*) as n, coalesce(sum(purchase_price), 0) as paid')->first();
        $s = $sold()->selectRaw('count(*) as n, coalesce(sum(sale_price), 0) as revenue, coalesce(sum(sale_price - purchase_price), 0) as profit, avg('.self::DAYS.') as days')->first();
        $stock = $base()->where('status', 'in_stock')->selectRaw('count(*) as n, coalesce(sum(purchase_price), 0) as cost, avg('.self::DAYS.') as days')->first();

        $summary = [['label' => 'اشتريت', 'value' => (int) $b->n, 'type' => 'int']];
        if ($query->withCost) {
            $summary[] = ['label' => 'دفعت فيهم', 'value' => (int) $b->paid, 'type' => 'money'];
        }
        $summary[] = ['label' => 'بعت', 'value' => (int) $s->n, 'type' => 'int', 'hint' => $s->days !== null ? 'قعدت في المتوسط '.round((float) $s->days).' يوم' : null];
        $summary[] = ['label' => 'إيراد البيع', 'value' => (int) $s->revenue, 'type' => 'money'];
        if ($query->withProfit) {
            $summary[] = ['label' => 'المكسب', 'value' => (int) $s->profit, 'type' => 'money', 'hint' => ($m = Labels::margin((int) $s->profit, (int) $s->revenue)) !== null ? "هامش {$m}%" : null];
        }
        $summary[] = ['label' => 'في المخزن دلوقتي', 'value' => (int) $stock->n, 'type' => 'int', 'hint' => $stock->days !== null ? 'متوسط عمرها '.round((float) $stock->days).' يوم' : null];
        if ($query->withCost) {
            $summary[] = ['label' => 'قيمتها بالتكلفة', 'value' => (int) $stock->cost, 'type' => 'money'];
        }
        $summary = array_map(fn ($x) => array_filter($x, fn ($v) => $v !== null), $summary);

        return match ($query->option('group', 'device')) {
            'model' => $this->grouped($query, $base, 'model_name', 'الموديل', $summary),
            'grade' => $this->grouped($query, $base, 'grade', 'الفئة', $summary),
            default => $this->byDevice($query, $base, $summary),
        };
    }

    /**
     * @param  \Closure(): Builder  $base
     * @param  list<array<string, mixed>>  $summary
     */
    private function byDevice(ReportQuery $query, \Closure $base, array $summary): ReportResult
    {
        $from = $query->fromUtc();
        $to = $query->toUtc();
        $rows = $base()
            ->where(fn (Builder $w) => $w->whereBetween('bought_at', [$from, $to])->orWhere(fn (Builder $x) => $x->where('status', 'sold')->whereBetween('sold_at', [$from, $to])))
            ->selectRaw("number, trim(model_name || ' ' || coalesce(storage, '') || ' ' || coalesce(color, '')) as name, grade, imei, status,
                ".ReportQuery::localDay('bought_at').' as bought_day, '.ReportQuery::localDay('sold_at').' as sold_day,
                purchase_price, sale_price, '.self::DAYS.' as days')
            ->orderBy('bought_at')
            ->limit(1000)
            ->get();

        $out = [];
        $totals = ['purchase_price' => 0, 'sale_price' => 0, 'profit' => 0];
        foreach ($rows as $r) {
            $sold = $r->status === 'sold';
            $row = [
                'reference' => 'UD-'.str_pad((string) $r->number, 5, '0', STR_PAD_LEFT),
                'name' => (string) $r->name,
                'grade' => (string) $r->grade,
                'imei' => (string) $r->imei,
                'bought_at' => (string) $r->bought_day,
                'sold_at' => $sold ? (string) $r->sold_day : null,
                'sale_price' => $sold ? (int) $r->sale_price : null,
                'days' => (int) floor((float) $r->days),
            ];
            if ($query->withCost) {
                $row['purchase_price'] = (int) $r->purchase_price;
                $totals['purchase_price'] += (int) $r->purchase_price;
            }
            if ($query->withProfit) {
                $row['profit'] = $sold ? (int) $r->sale_price - (int) $r->purchase_price : null;
                $totals['profit'] += $row['profit'] ?? 0;
            }
            $totals['sale_price'] += $row['sale_price'] ?? 0;
            $out[] = $row;
        }

        $columns = [new Column('reference', 'الجهاز'), new Column('name', 'الموديل'), new Column('grade', 'الفئة'), new Column('imei', 'IMEI'), new Column('bought_at', 'اتشرى', 'date')];
        if ($query->withCost) {
            $columns[] = new Column('purchase_price', 'اتشرى بـ', 'money');
        }
        array_push($columns, new Column('sold_at', 'اتباع', 'date'), new Column('sale_price', 'اتباع بـ', 'money'));
        if ($query->withProfit) {
            $columns[] = new Column('profit', 'المكسب', 'money');
        }
        $columns[] = new Column('days', 'أيام في المخزن', 'int');

        return new ReportResult(
            columns: $columns,
            rows: $out,
            summary: $summary,
            totals: ['reference' => 'الإجمالي', ...array_intersect_key($totals, array_flip(array_map(fn (Column $c) => $c->key, $columns)))],
            notes: ['الأجهزة اللي اتشرت أو اتباعت في الفترة. سعر البيع بعد نصيبه من خصم الفاتورة؛ المكسب = سعر البيع − اللي اتدفع فيه. أيام المخزن لحد ما اتباع، أو لحد النهارده.'],
        );
    }

    /**
     * @param  \Closure(): Builder  $base
     * @param  list<array<string, mixed>>  $summary
     */
    private function grouped(ReportQuery $query, \Closure $base, string $column, string $label, array $summary): ReportResult
    {
        $from = $query->fromUtc();
        $to = $query->toUtc();
        $rows = $base()
            ->groupBy($column)
            ->selectRaw("{$column} as name,
                count(*) filter (where bought_at between ? and ?) as bought,
                coalesce(sum(purchase_price) filter (where bought_at between ? and ?), 0) as paid,
                count(*) filter (where status = 'sold' and sold_at between ? and ?) as sold,
                coalesce(sum(sale_price) filter (where status = 'sold' and sold_at between ? and ?), 0) as revenue,
                coalesce(sum(sale_price - purchase_price) filter (where status = 'sold' and sold_at between ? and ?), 0) as profit,
                avg(".self::DAYS.") filter (where status = 'sold' and sold_at between ? and ?) as days_to_sell,
                count(*) filter (where status = 'in_stock') as in_stock,
                avg(".self::DAYS.") filter (where status = 'in_stock') as stock_age", [$from, $to, $from, $to, $from, $to, $from, $to, $from, $to, $from, $to])
            ->get()
            ->filter(fn ($r) => $r->bought > 0 || $r->sold > 0 || $r->in_stock > 0)
            ->sortByDesc(fn ($r) => [(int) $r->sold, (int) $r->bought])
            ->values();

        $out = [];
        $totals = ['bought' => 0, 'paid' => 0, 'sold' => 0, 'revenue' => 0, 'profit' => 0, 'in_stock' => 0];
        foreach ($rows as $r) {
            $row = [
                'name' => $column === 'grade' ? "فئة {$r->name}" : (string) $r->name,
                'bought' => (int) $r->bought,
                'sold' => (int) $r->sold,
                'revenue' => (int) $r->revenue,
                'days_to_sell' => $r->days_to_sell !== null ? (int) round((float) $r->days_to_sell) : null,
                'in_stock' => (int) $r->in_stock,
                'stock_age' => $r->stock_age !== null ? (int) round((float) $r->stock_age) : null,
            ];
            if ($query->withCost) {
                $row['paid'] = (int) $r->paid;
            }
            if ($query->withProfit) {
                $row['profit'] = (int) $r->profit;
                $row['margin'] = Labels::margin($row['profit'], $row['revenue']);
            }
            foreach ($totals as $k => $_) {
                $totals[$k] += $row[$k] ?? 0;
            }
            $out[] = $row;
        }

        $columns = [new Column('name', $label), new Column('bought', 'اشتريت', 'int')];
        if ($query->withCost) {
            $columns[] = new Column('paid', 'دفعت', 'money');
        }
        array_push($columns, new Column('sold', 'بعت', 'int'), new Column('revenue', 'الإيراد', 'money'));
        if ($query->withProfit) {
            array_push($columns, new Column('profit', 'المكسب', 'money'), new Column('margin', 'الهامش', 'percent'));
        }
        array_push($columns, new Column('days_to_sell', 'متوسط أيام البيع', 'int'), new Column('in_stock', 'في المخزن', 'int'), new Column('stock_age', 'متوسط عمر المخزون (يوم)', 'int'));
        $keys = array_map(fn (Column $c) => $c->key, $columns);

        return new ReportResult(
            columns: $columns,
            rows: $out,
            summary: $summary,
            totals: ['name' => 'الإجمالي', ...array_intersect_key($totals, array_flip($keys))],
            chart: $out === [] ? null : ['kind' => 'bars', 'label' => 'اتباع في الفترة', 'type' => 'int', 'points' => array_map(fn ($r) => ['label' => $r['name'], 'value' => $r['sold']], array_slice($out, 0, 10))],
            notes: ['اشتريت ودفعت: في الفترة. بعت والإيراد والمكسب: اللي اتباع في الفترة. في المخزن: دلوقتي.'],
        );
    }
}
