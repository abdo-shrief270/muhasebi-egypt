<?php

declare(strict_types=1);

namespace App\Modules\Services\Support;

use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Models\ServiceAccount;
use App\Modules\Services\Models\ServiceFeeRule;

/** The fee rules of an account, one per customer operation (none set = no fee). */
final class Fees
{
    public static function rule(ServiceAccount $account, OperationType $operation): FeeRule
    {
        $rule = $account->relationLoaded('feeRules')
            ? $account->feeRules->first(fn (ServiceFeeRule $r) => $r->operation === $operation)
            : $account->feeRules()->where('operation', $operation->value)->first();

        return $rule?->rule() ?? FeeRule::none();
    }

    /**
     * @return array<string, array{percent: int, fixed: int, min: int, max: int|null, round_to: int}>
     */
    public static function rulesOf(ServiceAccount $account): array
    {
        $out = [];
        foreach ($account->kind->operations() as $operation) {
            $out[$operation->value] = self::rule($account, $operation)->toArray();
        }

        return $out;
    }
}
