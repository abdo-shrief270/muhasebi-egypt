<?php

declare(strict_types=1);

namespace App\Modules\Services;

use App\Modules\Services\Contracts\ServiceProfits;
use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Support\DailyUsage;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;

final class ServiceProfitsService implements ServiceProfits
{
    public function daily(string $tenantId, CarbonImmutable $fromUtc): array
    {
        $day = "(created_at at time zone 'UTC' at time zone '".DailyUsage::TZ."')::date";

        return DB::table('service_transactions')
            ->where('tenant_id', $tenantId)
            ->where('created_at', '>=', $fromUtc)
            ->whereIn('type', array_map(fn (OperationType $t) => $t->value, OperationType::customer()))
            ->groupByRaw($day)
            ->selectRaw("{$day} as day, count(*) filter (where reverses_id is null) - count(*) filter (where reverses_id is not null) as operations, sum(profit) as profit")
            ->get()
            ->mapWithKeys(fn ($r) => [(string) $r->day => ['operations' => (int) $r->operations, 'profit' => (int) $r->profit]])
            ->all();
    }
}
