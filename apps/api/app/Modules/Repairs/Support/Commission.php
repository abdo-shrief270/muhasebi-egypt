<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Support;

use App\Modules\Repairs\Models\CommissionRule;
use App\Modules\Repairs\Models\RepairTicket;

/**
 * The technician's commission on a repaired device, by their rule: a percent of the labor
 * (after the discount comes off it) or of the profit (the bill less the parts' cost), or a fixed
 * amount per device. Warranty returns earn nothing: the work was already paid for.
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
        if ($rule === null) {
            return [0, null];
        }

        $amount = match (true) {
            $rule->type === 'fixed' => $rule->value,
            $rule->base === 'profit' => intdiv(max(0, $ticket->total - $ticket->parts_cost) * $rule->value + 5000, 10000),
            default => intdiv(max(0, $ticket->labor - $ticket->discount) * $rule->value + 5000, 10000),
        };

        return [$amount, $rule->describe()];
    }
}
