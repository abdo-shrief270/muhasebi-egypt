<?php

declare(strict_types=1);

namespace App\Modules\Installments\Support;

use Carbon\CarbonImmutable;

/**
 * How a plan's total is split (mirrored by `installmentSchedule()` in the web's utils/installments.ts):
 * equal installments rounded down to whole pounds, the last one takes what's left; a date every
 * $intervalMonths months from the first (the 31st falls back to the month's last day).
 */
final class Schedule
{
    /**
     * @return list<array{seq: int, due_on: string, amount: int}>
     */
    public static function make(int $total, int $count, CarbonImmutable $firstDue, int $intervalMonths = 1): array
    {
        $base = intdiv($total, $count);
        if ($base >= 100) {
            $base = intdiv($base, 100) * 100;
        }

        $items = [];
        for ($i = 0; $i < $count; $i++) {
            $items[] = [
                'seq' => $i + 1,
                'due_on' => $firstDue->addMonthsNoOverflow($i * $intervalMonths)->toDateString(),
                'amount' => $i === $count - 1 ? $total - $base * ($count - 1) : $base,
            ];
        }

        return $items;
    }

    /** The markup for a monthly rate (basis points) over the plan's months, rounded to whole pounds. */
    public static function markup(int $principal, int $rateBasisPoints, int $months): int
    {
        return (int) (round($principal * $rateBasisPoints * $months / 10000 / 100) * 100);
    }
}
