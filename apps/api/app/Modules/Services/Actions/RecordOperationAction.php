<?php

declare(strict_types=1);

namespace App\Modules\Services\Actions;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Enums\WithdrawFeeMode;
use App\Modules\Services\Models\ServiceTransaction;
use App\Modules\Services\Support\DailyUsage;
use App\Modules\Services\Support\Fees;
use App\Modules\Services\Support\ServiceLedger;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * A customer operation at the counter, in one transaction: the account's balance, the cash in the
 * user's drawer (their open shift), and the fee / profit on the row.
 *
 *  - deposit  (إيداع):   the customer pays amount + fee in cash, the wallet sends amount.
 *  - withdraw (سحب):     the wallet receives amount (+ fee in «wallet» mode), the customer takes
 *                        amount − fee in cash («cash» mode) or the whole amount («wallet» mode).
 *  - topup    (شحن):     the customer pays face + fee, the airtime balance goes down by face; the
 *                        profit is the fee plus the distributor's discount on that face value.
 */
final class RecordOperationAction
{
    public function __construct(
        private readonly ServiceLedger $ledger,
        private readonly CashDrawer $drawer,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  int|null  $fee  null = the suggested fee
     * @param  array{customer_name?: string|null, customer_phone?: string|null, reference?: string|null, note?: string|null}  $details
     * @return array{0: ServiceTransaction, 1: string|null} the row and a warning (daily limit passed)
     */
    public function handle(string $branchId, string $accountId, OperationType $type, int $amount, ?int $fee, bool $mayEditFee, array $details = []): array
    {
        return DB::transaction(function () use ($branchId, $accountId, $type, $amount, $fee, $mayEditFee, $details): array {
            $account = $this->ledger->lock($accountId);
            if ($account->branch_id !== $branchId) {
                throw new DomainRuleException('الحساب ده مش في الفرع ده.', 'account_other_branch');
            }
            if (! $account->is_active) {
                throw new DomainRuleException('الحساب ده متوقف.', 'account_inactive');
            }
            if (! in_array($type, $account->kind->operations(), true)) {
                throw new DomainRuleException("«{$type->label()}» مش بيتعمل على {$account->kind->label()}.", 'operation_not_allowed');
            }

            $suggested = Fees::rule($account, $type)->feeFor($amount);
            $fee ??= $suggested;
            if ($fee !== $suggested && ! $mayEditFee) {
                throw new DomainRuleException('مش مسموح لك تغيّر العمولة. العمولة المقترحة '.ServiceLedger::money($suggested).' ج.', 'fee_locked', 403, ['suggested_fee' => $suggested]);
            }

            $mode = null;
            switch ($type) {
                case OperationType::Deposit:
                    $move = ['balance_change' => -$amount, 'cost_change' => -ServiceLedger::costOf($account, $amount), 'cash' => $amount + $fee, 'profit' => $fee];
                    break;
                case OperationType::Withdraw:
                    $mode = $account->withdraw_fee_mode;
                    if ($mode === WithdrawFeeMode::Cash && $fee > $amount) {
                        throw new DomainRuleException('العمولة أكبر من المبلغ.', 'fee_too_high');
                    }
                    $received = $mode === WithdrawFeeMode::Wallet ? $amount + $fee : $amount;
                    $move = ['balance_change' => $received, 'cost_change' => $received, 'cash' => -($mode === WithdrawFeeMode::Wallet ? $amount : $amount - $fee), 'profit' => $fee];
                    break;
                default: // top-up
                    $cost = ServiceLedger::costOf($account, $amount);
                    $move = ['balance_change' => -$amount, 'cost_change' => -$cost, 'cash' => $amount + $fee, 'profit' => $amount + $fee - $cost];
            }

            $transaction = $this->ledger->post($account, $type, [
                ...$move,
                'amount' => $amount,
                'fee' => $fee,
                'suggested_fee' => $suggested,
                'fee_mode' => $mode?->value,
                'customer_name' => $details['customer_name'] ?? null,
                'customer_phone' => $details['customer_phone'] ?? null,
                'reference' => $details['reference'] ?? null,
                'note' => $details['note'] ?? null,
            ]);

            $label = "{$type->label()} ".ServiceLedger::money($amount)." ج — {$account->name}";
            // Cash always needs the user's open shift: no shift, nothing is saved.
            $this->drawer->record($branchId, DrawerEntry::Service, 'cash', $move['cash'], 'service_transaction', $transaction->id, $label);

            $this->audit->record(
                "services.{$type->value}",
                $label.' (عمولة '.ServiceLedger::money($fee).' ج'.($fee !== $suggested ? '، المقترحة '.ServiceLedger::money($suggested).' ج' : '').')',
                $transaction,
                ['amount' => $amount, 'fee' => $fee, 'suggested_fee' => $suggested, 'account_id' => $account->id],
            );

            return [$transaction, DailyUsage::warning($account, $type)];
        });
    }
}
