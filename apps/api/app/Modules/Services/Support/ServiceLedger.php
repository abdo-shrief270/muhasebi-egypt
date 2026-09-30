<?php

declare(strict_types=1);

namespace App\Modules\Services\Support;

use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Models\ServiceAccount;
use App\Modules\Services\Models\ServiceTransaction;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Contracts\Auth\Factory as Auth;

/**
 * Moves a service account's balance (and what that balance cost) under a row lock, in step with
 * the append-only service_transactions. Call inside the caller's transaction.
 */
final class ServiceLedger
{
    public function __construct(
        private readonly Auth $auth,
        private readonly DocumentNumbers $numbers,
    ) {}

    public function lock(string $accountId): ServiceAccount
    {
        return ServiceAccount::query()->lockForUpdate()->findOrFail($accountId);
    }

    /**
     * What $units of the balance cost the shop: its share of cost_value (airtime bought at a
     * discount costs less than its face value; a wallet's money costs exactly its amount).
     */
    public static function costOf(ServiceAccount $account, int $units): int
    {
        if ($account->balance <= 0 || $account->cost_value <= 0) {
            return $units;
        }
        if ($units >= $account->balance) {
            return $account->cost_value;
        }

        return intdiv(2 * $units * $account->cost_value + $account->balance, 2 * $account->balance);
    }

    /**
     * @param  array{amount: int, balance_change: int, cost_change: int, fee?: int, suggested_fee?: int|null, cash?: int, profit?: int,
     *     fee_mode?: string|null, source?: string|null, customer_name?: string|null, customer_phone?: string|null,
     *     reference?: string|null, note?: string|null, reverses_id?: string|null}  $data
     *
     * @throws DomainRuleException insufficient_balance
     */
    public function post(ServiceAccount $account, OperationType $type, array $data): ServiceTransaction
    {
        $balance = $account->balance + $data['balance_change'];
        if ($balance < 0) {
            throw new DomainRuleException(
                'رصيد «'.$account->name.'» مش كفاية: فيه '.self::money($account->balance).' ج بس.',
                'insufficient_balance',
                context: ['balance' => $account->balance],
            );
        }
        $account->balance = $balance;
        // Emptied → nothing left to cost.
        $account->cost_value = $balance === 0 ? 0 : max(0, $account->cost_value + $data['cost_change']);
        $costChange = $account->cost_value - $account->getOriginal('cost_value');
        $account->save();

        $user = $this->auth->guard('sanctum')->user();

        return ServiceTransaction::create([
            'fee' => 0,
            'cash' => 0,
            'profit' => 0,
            ...$data,
            'tenant_id' => $account->tenant_id,
            'branch_id' => $account->branch_id,
            'account_id' => $account->id,
            'number' => $this->numbers->next($account->tenant_id, 'service'),
            'type' => $type,
            'balance_after' => $account->balance,
            'cost_change' => $costChange,
            'cost_after' => $account->cost_value,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name'),
            'created_at' => now(),
        ]);
    }

    public static function money(int $piasters): string
    {
        return rtrim(rtrim(number_format($piasters / 100, 2, '.', ','), '0'), '.');
    }
}
