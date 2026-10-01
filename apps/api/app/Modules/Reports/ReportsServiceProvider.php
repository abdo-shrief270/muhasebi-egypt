<?php

declare(strict_types=1);

namespace App\Modules\Reports;

use App\Modules\Reports\Contracts\DailySummary;
use App\Support\Modules\ModuleServiceProvider;

final class ReportsServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(DailySummary::class, DailySummaryService::class);
    }
}
