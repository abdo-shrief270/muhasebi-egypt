<?php

declare(strict_types=1);

namespace App\Modules\Services\Support;

use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Models\ServiceAccount;
use App\Modules\Services\Models\ServiceTransaction;
use Carbon\CarbonImmutable;

/**
 * How much went through an account today (Cairo day): customer operations, net of reversals. Wallets
 * have daily limits set by the operator; the shop's own limit only warns, it never blocks.
 */
final class DailyUsage
{
    public const TZ = 'Africa/Cairo';

    /**
     * @param  list<string>  $accountIds
     * @return array<string, int>
     */
    public static function today(array $accountIds): array
    {
        if ($accountIds === []) {
            return [];
        }

        return ServiceTransaction::query()
            ->whereIn('account_id', $accountIds)
            ->whereIn('type', array_map(fn (OperationType $t) => $t->value, OperationType::customer()))
            ->where('created_at', '>=', CarbonImmutable::now(self::TZ)->startOfDay()->utc())
            ->groupBy('account_id')
            ->selectRaw('account_id, sum(amount) as used')
            ->pluck('used', 'account_id')
            ->map(fn ($v) => (int) $v)
            ->all();
    }

    public static function warning(ServiceAccount $account, OperationType $type): ?string
    {
        if ($account->daily_limit === null || ! $type->isCustomer()) {
            return null;
        }
        $used = self::today([$account->id])[$account->id] ?? 0;

        return $used > $account->daily_limit
            ? "«{$account->name}» عدّى الحد اليومي: اتحوّل النهارده ".ServiceLedger::money($used).' ج من '.ServiceLedger::money($account->daily_limit).' ج.'
            : null;
    }
}
