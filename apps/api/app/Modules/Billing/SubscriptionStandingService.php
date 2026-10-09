<?php

declare(strict_types=1);

namespace App\Modules\Billing;

use App\Modules\Billing\Contracts\SubscriptionStanding;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\SubscriptionStatus;
use Illuminate\Support\Carbon;

final class SubscriptionStandingService implements SubscriptionStanding
{
    private const LISTED = [SubscriptionStatus::Trialing, SubscriptionStatus::Active, SubscriptionStatus::PastDue];

    public function listed(string $tenantId): bool
    {
        $s = Subscription::withoutTenancy()->where('tenant_id', $tenantId)->first();

        return $s !== null && in_array(SubscriptionStatus::of($s, Carbon::now()), self::LISTED, true);
    }

    public function listedTenants(): array
    {
        $now = Carbon::now();
        $ids = [];
        Subscription::withoutTenancy()->orderBy('tenant_id')->each(function (Subscription $s) use ($now, &$ids): void {
            if (in_array(SubscriptionStatus::of($s, $now), self::LISTED, true)) {
                $ids[] = $s->tenant_id;
            }
        });

        return $ids;
    }

    public function status(string $tenantId): string
    {
        $s = Subscription::withoutTenancy()->where('tenant_id', $tenantId)->first();

        return $s === null ? 'none' : SubscriptionStatus::of($s, Carbon::now())->value;
    }
}
