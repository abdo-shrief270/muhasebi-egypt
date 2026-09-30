<?php

declare(strict_types=1);

namespace App\Modules\Services\Actions;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Services\Enums\AccountKind;
use App\Modules\Services\Enums\MoneySource;
use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Models\ServiceTransaction;
use App\Modules\Services\Support\ServiceLedger;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Money between the shop and one of its accounts, no customer involved:
 *  - fund (تمويل): the account gets $amount, paid $paid from the drawer or the safe. Airtime is
 *    bought from a distributor at a discount ($paid < $amount); a wallet is funded 1:1.
 *  - cash out (تسييل): $amount leaves a wallet into the drawer or the safe.
 * From / to the drawer goes through CashDrawer on the user's open shift; the safe isn't a drawer.
 */
final class MoveAccountMoneyAction
{
    public function __construct(
        private readonly ServiceLedger $ledger,
        private readonly CashDrawer $drawer,
        private readonly Auditor $audit,
    ) {}

    public function handle(string $branchId, string $accountId, OperationType $type, int $amount, ?int $paid, MoneySource $source, ?string $note): ServiceTransaction
    {
        return DB::transaction(function () use ($branchId, $accountId, $type, $amount, $paid, $source, $note): ServiceTransaction {
            $account = $this->ledger->lock($accountId);
            if ($account->branch_id !== $branchId) {
                throw new DomainRuleException('الحساب ده مش في الفرع ده.', 'account_other_branch');
            }

            if ($type === OperationType::Fund) {
                $paid ??= $amount;
                if ($account->kind === AccountKind::Wallet && $paid !== $amount) {
                    throw new DomainRuleException('المحفظة بتتموّل بنفس المبلغ.', 'wallet_fund_at_par');
                }
                if ($paid > $amount) {
                    throw new DomainRuleException('المدفوع أكبر من الرصيد اللي هيدخل.', 'fund_over_face');
                }
                $move = ['balance_change' => $amount, 'cost_change' => $paid, 'cash' => $source === MoneySource::Drawer ? -$paid : 0];
                $label = "تمويل «{$account->name}» بـ ".ServiceLedger::money($amount).' ج'.($paid !== $amount ? ' (اتدفع '.ServiceLedger::money($paid).' ج)' : '')." من {$source->label()}";
            } else {
                if ($account->kind !== AccountKind::Wallet) {
                    throw new DomainRuleException('رصيد الشحن مش بيتسيّل.', 'operation_not_allowed');
                }
                $move = ['balance_change' => -$amount, 'cost_change' => -ServiceLedger::costOf($account, $amount), 'cash' => $source === MoneySource::Drawer ? $amount : 0];
                $label = 'تسييل '.ServiceLedger::money($amount)." ج من «{$account->name}» لـ{$source->label()}";
            }

            $transaction = $this->ledger->post($account, $type, [...$move, 'amount' => $amount, 'source' => $source->value, 'note' => $note]);
            $this->drawer->record($branchId, DrawerEntry::Service, 'cash', $move['cash'], 'service_transaction', $transaction->id, $label);
            $this->audit->record("services.{$type->value}", $label, $transaction, ['amount' => $amount, 'paid' => $paid, 'source' => $source->value, 'account_id' => $account->id]);

            return $transaction;
        });
    }
}
