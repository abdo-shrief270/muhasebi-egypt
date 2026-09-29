<?php

declare(strict_types=1);

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Enums\Method;
use App\Modules\Cash\Models\CashShift;
use App\Modules\Cash\Support\ShiftTotals;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * Ends a shift: what the drawer should hold per payment method against what was counted.
 * A method that wasn't counted (card, wallet) is taken as matching.
 */
final class CloseShiftAction
{
    public function __construct(private readonly Auditor $audit) {}

    /**
     * @param  array<string, int>  $counted  piasters per method; cash is required
     */
    public function handle(CashShift $shift, Authenticatable $by, array $counted, ?string $note): CashShift
    {
        return DB::transaction(function () use ($shift, $by, $counted, $note): CashShift {
            $shift = CashShift::query()->lockForUpdate()->findOrFail($shift->id);
            if (! $shift->isOpen()) {
                throw new DomainRuleException('الوردية دي اتقفلت قبل كده.', 'shift_closed', 409);
            }

            $expected = ShiftTotals::for($shift)['expected'];
            $final = [];
            foreach (Method::cases() as $method) {
                $final[$method->value] = $counted[$method->value] ?? $expected[$method->value];
            }
            $difference = $final[Method::Cash->value] - $expected[Method::Cash->value];

            $shift->update([
                'closed_at' => now(),
                'closed_by' => $by->getAuthIdentifier(),
                'closed_by_name' => (string) $by->getAttribute('name'),
                'expected' => $expected,
                'counted' => $final,
                'cash_difference' => $difference,
                'note' => $note ?? $shift->note,
            ]);

            $this->audit->record(
                'cash.shift_closed',
                "قفل الوردية {$shift->reference()} ({$shift->user_name})"
                    .($difference === 0 ? ' والدرج مظبوط' : ($difference < 0 ? ' بعجز ' : ' بزيادة ').number_format(abs($difference) / 100, 2).' ج'),
                $shift,
                ['expected' => $expected, 'counted' => $final, 'cash_difference' => $difference],
            );

            return $shift;
        });
    }
}
