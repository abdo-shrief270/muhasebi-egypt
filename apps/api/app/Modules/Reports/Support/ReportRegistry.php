<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

use App\Modules\Reports\Definitions\ExpensesReport;
use App\Modules\Reports\Definitions\InventoryReport;
use App\Modules\Reports\Definitions\OpenSupplierReturnsReport;
use App\Modules\Reports\Definitions\PayablesReport;
use App\Modules\Reports\Definitions\PaymentsReport;
use App\Modules\Reports\Definitions\ProductsReport;
use App\Modules\Reports\Definitions\ReceivablesReport;
use App\Modules\Reports\Definitions\RepairsReport;
use App\Modules\Reports\Definitions\SalesReport;
use App\Modules\Reports\Definitions\ServicesReport;
use App\Modules\Reports\Definitions\ShiftsReport;
use App\Modules\Reports\Definitions\StaffReport;
use App\Modules\Reports\Definitions\SupplierReturnRateReport;
use Illuminate\Contracts\Auth\Access\Authorizable;

final class ReportRegistry
{
    /** In the order the reports page lists them. */
    private const REPORTS = [
        SalesReport::class,
        ProductsReport::class,
        StaffReport::class,
        PaymentsReport::class,
        RepairsReport::class,
        ServicesReport::class,
        InventoryReport::class,
        ReceivablesReport::class,
        PayablesReport::class,
        SupplierReturnRateReport::class,
        OpenSupplierReturnsReport::class,
        ExpensesReport::class,
        ShiftsReport::class,
    ];

    /**
     * @return list<Report>
     */
    public function all(): array
    {
        return array_map(fn (string $class): Report => app($class), self::REPORTS);
    }

    /**
     * @return list<Report>
     */
    public function visibleTo(Authorizable $user): array
    {
        return array_values(array_filter($this->all(), fn (Report $r) => $this->canSee($user, $r)));
    }

    public function find(string $key): ?Report
    {
        foreach ($this->all() as $report) {
            if ($report->key() === $key) {
                return $report;
            }
        }

        return null;
    }

    public function canSee(Authorizable $user, Report $report): bool
    {
        return $user->can('reports.view') && ($report->permission() === null || $user->can($report->permission()));
    }
}
