<?php

declare(strict_types=1);

namespace App\Modules\Reports\Contracts;

/** The shop's day in a few lines (all branches), for the owner's end-of-day notification. */
interface DailySummary
{
    /**
     * @return array{net: int, invoices: int, lines: list<string>}
     */
    public function for(string $tenantId, string $day): array;
}
