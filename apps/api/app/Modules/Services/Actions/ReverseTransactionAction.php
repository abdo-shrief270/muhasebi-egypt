<?php

declare(strict_types=1);

namespace App\Modules\Services\Actions;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Models\ServiceTransaction;
use App\Modules\Services\Support\ServiceLedger;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Corrects a mistake: a new row that cancels the original (same type, every amount negated,
 * reverses_id set). The account's balance goes back and the cash goes back through the user's drawer.
 */
final class ReverseTransactionAction
{
    public function __construct(
        private readonly ServiceLedger $ledger,
        private readonly CashDrawer $drawer,
        private readonly Auditor $audit,
    ) {}

    public function handle(string $branchId, ServiceTransaction $original, string $reason): ServiceTransaction
    {
        return DB::transaction(function () use ($branchId, $original, $reason): ServiceTransaction {
            $account = $this->ledger->lock($original->account_id);
            if ($original->branch_id !== $branchId) {
                throw new DomainRuleException('العملية دي مش في الفرع ده.', 'account_other_branch');
            }
            if ($original->isReversal()) {
                throw new DomainRuleException('ده إلغاء بالفعل.', 'already_reversal');
            }
            if ($original->type === OperationType::Opening) {
                throw new DomainRuleException('الرصيد الافتتاحي مش بيتلغي؛ صحّحه بتمويل أو تسييل من الخزنة.', 'opening_not_reversible');
            }
            if (ServiceTransaction::query()->where('reverses_id', $original->id)->exists()) {
                throw new DomainRuleException('العملية دي اتلغت قبل كده.', 'already_reversed', 409);
            }

            $reversal = $this->ledger->post($account, $original->type, [
                'amount' => -$original->amount,
                'fee' => -$original->fee,
                'balance_change' => -$original->balance_change,
                'cost_change' => -$original->cost_change,
                'cash' => -$original->cash,
                'profit' => -$original->profit,
                'fee_mode' => $original->fee_mode?->value,
                'source' => $original->source?->value,
                'customer_name' => $original->customer_name,
                'customer_phone' => $original->customer_phone,
                'reference' => $original->reference,
                'note' => $reason,
                'reverses_id' => $original->id,
            ]);

            $label = "إلغاء {$original->type->label()} ".ServiceLedger::money($original->amount)." ج — {$account->name} (#{$original->number})";
            $this->drawer->record($branchId, DrawerEntry::Service, 'cash', -$original->cash, 'service_transaction', $reversal->id, $label);
            $this->audit->record('services.reversed', "{$label}: {$reason}", $reversal, ['reverses_id' => $original->id, 'amount' => $original->amount]);

            return $reversal;
        });
    }
}
