<?php

declare(strict_types=1);

namespace App\Modules\Cash\Support;

use App\Modules\Cash\Enums\Method;
use App\Modules\Cash\Enums\MovementType;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashShift;

/**
 * What a shift should hold: the opening cash plus every movement, per payment method, and the
 * same broken down by movement type for the close report.
 */
final class ShiftTotals
{
    /**
     * @return array{expected: array<string, int>, by_type: list<array{type: string, label: string, method: string, amount: int, count: int}>}
     */
    public static function for(CashShift $shift): array
    {
        $rows = CashMovement::query()
            ->where('shift_id', $shift->id)
            ->groupBy('type', 'method')
            ->selectRaw('type, method, sum(amount) as amount, count(*) as count')
            ->get();

        $expected = [];
        foreach (Method::cases() as $method) {
            $expected[$method->value] = $method === Method::Cash ? $shift->opening_cash : 0;
        }
        $byType = [];
        foreach ($rows as $row) {
            // The model casts the grouped columns back to their enums.
            $type = $row->getAttribute('type');
            $type = $type instanceof MovementType ? $type : MovementType::from((string) $type);
            $method = $row->getAttribute('method');
            $method = $method instanceof Method ? $method->value : (string) $method;
            $amount = (int) $row->getAttribute('amount');
            $expected[$method] = ($expected[$method] ?? 0) + $amount;
            $byType[] = ['type' => $type->value, 'label' => $type->label(), 'method' => $method, 'amount' => $amount, 'count' => (int) $row->getAttribute('count')];
        }

        usort($byType, fn (array $a, array $b) => [array_search($a['type'], array_column(MovementType::cases(), 'value'), true), $a['method']]
            <=> [array_search($b['type'], array_column(MovementType::cases(), 'value'), true), $b['method']]);

        return ['expected' => $expected, 'by_type' => $byType];
    }

    public static function cash(CashShift $shift): int
    {
        return $shift->opening_cash + (int) CashMovement::query()
            ->where('shift_id', $shift->id)
            ->where('method', Method::Cash->value)
            ->sum('amount');
    }
}
