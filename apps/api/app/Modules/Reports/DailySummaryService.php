<?php

declare(strict_types=1);

namespace App\Modules\Reports;

use App\Modules\Reports\Contracts\DailySummary;
use App\Modules\Reports\Definitions\SalesReport;
use App\Modules\Reports\Support\ReportQuery;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

/** The sales report for one day, every branch, with profit (the summary goes to whoever may see the owner alerts). */
final class DailySummaryService implements DailySummary
{
    public function __construct(private readonly SalesReport $sales) {}

    public function for(string $tenantId, string $day): array
    {
        $date = CarbonImmutable::parse($day, ReportQuery::TZ);
        $branches = DB::table('branches')->where('tenant_id', $tenantId)->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
        $result = $this->sales->run(new ReportQuery($tenantId, $date, $date, $branches, withProfit: true, withCost: false));

        $values = [];
        $lines = [];
        foreach ($result->summary as $item) {
            $values[$item['label']] = (int) $item['value'];
            if ($item['label'] === 'متوسط الفاتورة' || ((int) $item['value'] === 0 && $item['label'] !== 'صافي المبيعات')) {
                continue;
            }
            $value = $item['type'] === 'money' ? number_format((int) $item['value'] / 100).' ج' : (string) $item['value'];
            $lines[] = "{$item['label']}: {$value}";
        }

        return ['net' => $values['صافي المبيعات'] ?? 0, 'invoices' => $values['الفواتير'] ?? 0, 'lines' => $lines];
    }
}
