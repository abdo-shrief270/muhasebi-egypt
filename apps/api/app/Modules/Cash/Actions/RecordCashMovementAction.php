<?php

declare(strict_types=1);

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Contracts\ExpenseCategory;
use App\Modules\Cash\Enums\Method;
use App\Modules\Cash\Enums\MovementType;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashShift;
use App\Modules\Cash\Support\ShiftTotals;
use App\Modules\OwnerApp\Contracts\ApprovalKind;
use App\Modules\OwnerApp\Contracts\Approvals;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Facades\DB;

/**
 * An expense paid from the drawer, or cash put in / taken out (to the owner, the bank, change
 * from next door). Always cash, always on the user's open shift.
 */
final class RecordCashMovementAction
{
    public function __construct(
        private readonly Auditor $audit,
        private readonly Approvals $approvals,
    ) {}

    /**
     * @param  int  $amount  piasters, > 0
     */
    public function handle(string $branchId, Authenticatable $user, MovementType $type, int $amount, ?ExpenseCategory $category, ?string $note): CashMovement
    {
        if (! $type->isManual()) {
            throw new DomainRuleException('نوع الحركة ده بيتسجل لوحده من البيع والتحصيل.', 'movement_not_manual');
        }

        return DB::transaction(function () use ($branchId, $user, $type, $amount, $category, $note): CashMovement {
            $shift = CashShift::query()
                ->where('branch_id', $branchId)
                ->where('user_id', $user->getAuthIdentifier())
                ->whereNull('closed_at')
                ->lockForUpdate()
                ->first() ?? throw new DomainRuleException('افتح وردية الأول عشان الفلوس تتسجل في درجك.', 'shift_not_open', 409);

            // Past the owner's limit, cash going out needs an OK (from the owner's phone or a manager's PIN).
            if ($type !== MovementType::Deposit) {
                $this->approvals->require(
                    [[ApprovalKind::Withdrawal, $amount, ($type === MovementType::Expense ? 'مصروف ' : 'سحب ').number_format($amount / 100, 2).' ج من الدرج'.($note ? " — {$note}" : '')]],
                    ['type' => $type->value, 'amount' => $amount, 'note' => $note, 'shift' => $shift->id],
                    $amount,
                    $branchId,
                );
            }

            $signed = $type === MovementType::Deposit ? $amount : -$amount;
            $inDrawer = ShiftTotals::cash($shift);
            if ($signed < 0 && $inDrawer + $signed < 0) {
                throw new DomainRuleException(
                    'الدرج فيه '.number_format($inDrawer / 100, 2).' ج بس.',
                    'insufficient_cash',
                    context: ['available' => $inDrawer],
                );
            }

            $movement = CashMovement::create([
                'tenant_id' => $shift->tenant_id,
                'branch_id' => $branchId,
                'shift_id' => $shift->id,
                'type' => $type,
                'method' => Method::Cash,
                'amount' => $signed,
                'category' => $type === MovementType::Expense ? ($category ?? ExpenseCategory::Other) : null,
                'note' => $note,
                'user_id' => $user->getAuthIdentifier(),
                'user_name' => (string) $user->getAttribute('name'),
                'created_at' => now(),
            ]);

            $what = $type === MovementType::Expense ? "مصروف ({$movement->category?->label()})" : $type->label();
            $this->audit->record(
                "cash.{$type->value}",
                "{$what} ".number_format($amount / 100, 2)." ج في الوردية {$shift->reference()}".($note ? " — {$note}" : ''),
                $movement,
                ['amount' => $signed, 'shift_id' => $shift->id],
            );

            return $movement;
        });
    }
}
