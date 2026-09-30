<?php

declare(strict_types=1);

namespace App\Modules\Services\Actions;

use App\Modules\Services\Enums\AccountKind;
use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Models\ServiceAccount;
use App\Modules\Services\Models\ServiceFeeRule;
use App\Modules\Services\Support\ServiceLedger;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Adds or edits a wallet / airtime line and its fee rules. The opening balance (only when adding)
 * goes through the ledger like every other move; the balance itself is never edited.
 */
final class SaveAccountAction
{
    public function __construct(
        private readonly ServiceLedger $ledger,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  name, provider, phone, daily_limit, withdraw_fee_mode, is_active (+ kind when adding)
     * @param  array<string, array{percent?: int, fixed?: int, min?: int, max?: int|null, round_to?: int}>|null  $fees  per operation
     */
    public function handle(string $tenantId, string $branchId, array $data, ?array $fees, ?ServiceAccount $account = null, int $opening = 0, ?int $openingCost = null): ServiceAccount
    {
        return DB::transaction(function () use ($tenantId, $branchId, $data, $fees, $account, $opening, $openingCost): ServiceAccount {
            $creating = $account === null;
            $account ??= new ServiceAccount(['tenant_id' => $tenantId, 'branch_id' => $branchId, 'kind' => $data['kind']]);
            $account->fill(array_intersect_key($data, array_flip(['provider', 'name', 'phone', 'daily_limit', 'withdraw_fee_mode', 'is_active'])));
            $account->save();

            if ($fees !== null) {
                foreach ($fees as $operation => $rule) {
                    $operation = OperationType::from($operation);
                    if (! in_array($operation, $account->kind->operations(), true)) {
                        continue;
                    }
                    ServiceFeeRule::query()->updateOrCreate(
                        ['account_id' => $account->id, 'operation' => $operation->value],
                        [
                            'tenant_id' => $tenantId,
                            'percent' => (int) ($rule['percent'] ?? 0),
                            'fixed' => (int) ($rule['fixed'] ?? 0),
                            'min' => (int) ($rule['min'] ?? 0),
                            'max' => isset($rule['max']) ? (int) $rule['max'] : null,
                            'round_to' => (int) ($rule['round_to'] ?? 0),
                        ],
                    );
                }
            }

            if ($creating && $opening > 0) {
                $cost = $openingCost ?? $opening;
                if ($account->kind === AccountKind::Wallet && $cost !== $opening) {
                    throw new DomainRuleException('المحفظة رصيدها بنفس قيمته.', 'wallet_fund_at_par');
                }
                $this->ledger->post($this->ledger->lock($account->id), OperationType::Opening, [
                    'amount' => $opening, 'balance_change' => $opening, 'cost_change' => min($cost, $opening),
                ]);
            }

            $this->audit->record(
                $creating ? 'services.account_created' : 'services.account_updated',
                ($creating ? 'إضافة' : 'تعديل')." {$account->kind->label()} «{$account->name}»".($creating && $opening > 0 ? ' برصيد '.ServiceLedger::money($opening).' ج' : ''),
                $account,
                ['fees' => $fees],
            );

            return $account->refresh()->load('feeRules');
        });
    }
}
