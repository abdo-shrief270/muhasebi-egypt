<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WalletTransaction;
use App\Support\Exceptions\DomainRuleException;

/**
 * The shop's credit (piasters) and points: the balance on its subscription and the append-only
 * ledger move together under a row lock. Call inside a transaction.
 */
final class Wallet
{
    /** @param  'credit'|'points'  $unit */
    public function post(string $tenantId, string $unit, string $type, int $amount, ?string $refType = null, ?string $refId = null, ?string $note = null): WalletTransaction
    {
        $id = app(Subscriptions::class)->for($tenantId)->id;
        $subscription = Subscription::withoutTenancy()->lockForUpdate()->findOrFail($id);
        $column = $unit === 'credit' ? 'credit_balance' : 'points_balance';
        $balance = $subscription->{$column} + $amount;
        if ($balance < 0) {
            throw new DomainRuleException($unit === 'credit' ? 'الرصيد مش كفاية.' : 'النقاط مش كفاية.', 'wallet_insufficient');
        }
        $subscription->update([$column => $balance]);

        return WalletTransaction::withoutTenancy()->create([
            'tenant_id' => $tenantId,
            'unit' => $unit,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $balance,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'note' => $note !== null ? mb_substr($note, 0, 255) : null,
            'created_at' => now(),
        ]);
    }

    /** Whether the shop already got this once-only reward. */
    public function got(string $tenantId, string $type): bool
    {
        return WalletTransaction::withoutTenancy()->where('tenant_id', $tenantId)->where('type', $type)->exists();
    }
}
