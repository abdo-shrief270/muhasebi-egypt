<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Support;

use App\Modules\Repairs\Models\CommissionRule;
use App\Modules\Repairs\Models\RepairTicket;

/**
 * The technician's commission on a repaired device, by their rule: what's set of a percent of the
 * labor (after the discount comes off it), a percent of the parts (their price, or their price
 * less their cost), a percent of the profit (the bill less what the repair cost) and a fixed
 * amount, added up. Warranty returns earn nothing: the work was already paid for (whether the
 * first ticket's commission is taken back is the rule's `warranty_clawback`, settled in payroll).
 */
final class Commission
{
    /**
     * @return array{0: int, 1: string|null} amount (piasters), the rule as words
     */
    public static function for(RepairTicket $ticket): array
    {
        if ($ticket->technician_id === null || $ticket->warranty_of_id !== null) {
            return [0, null];
        }
        $rule = CommissionRule::query()->where('user_id', $ticket->technician_id)->first();
        if ($rule === null || $rule->isEmpty()) {
            return [0, null];
        }

        $share = fn (int $base, int $bp): int => $bp === 0 ? 0 : intdiv(max(0, $base) * $bp + 5000, 10000);
        $parts = $rule->parts_base === 'profit' ? $ticket->parts_total - $ticket->parts_cost : $ticket->parts_total;
        $amount = $share($ticket->labor - $ticket->discount, $rule->labor_rate)
            + $share($parts, $rule->parts_rate)
            + $share($ticket->total - $ticket->cost(), $rule->profit_rate)
            + $rule->fixed;

        return [$amount, mb_substr($rule->describe(), 0, 160)];
    }
}
