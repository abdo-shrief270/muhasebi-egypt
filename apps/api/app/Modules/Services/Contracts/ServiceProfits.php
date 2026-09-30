<?php

declare(strict_types=1);

namespace App\Modules\Services\Contracts;

use Carbon\CarbonImmutable;

/** The services' profit (fees + airtime margins, net of reversals), for the dashboard. Kept apart from goods profit. */
interface ServiceProfits
{
    /**
     * @return array<string, array{operations: int, profit: int}> by Cairo day (Y-m-d), only days with operations
     */
    public function daily(string $tenantId, CarbonImmutable $fromUtc): array;
}
