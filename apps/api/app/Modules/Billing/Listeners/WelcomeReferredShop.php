<?php

declare(strict_types=1);

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\Subscriptions;
use App\Modules\Identity\Events\TenantRegistered;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use Illuminate\Support\Facades\DB;

/**
 * A shop registered with another's invite code: it gets the welcome discount (billing.rewards.
 * referral_discount), and the inviter is remembered for its points on the first real payment.
 */
final class WelcomeReferredShop extends ModuleListener
{
    protected function module(): string
    {
        return 'billing';
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof TenantRegistered);
        if ($event->referredBy === null || $event->referredBy === $event->tenantId) {
            return;
        }
        $welcome = (array) config('billing.rewards.referral_discount');
        DB::transaction(function () use ($event, $welcome): void {
            $subscription = Subscription::withoutTenancy()->lockForUpdate()->find(app(Subscriptions::class)->for($event->tenantId)->id);
            if ($subscription->referred_by !== null) {
                return;
            }
            $subscription->update(['referred_by' => $event->referredBy]);
            // One welcome discount per shop (a partner's link may have given it already).
            if ((int) ($welcome['percent'] ?? 0) > 0
                && ! CouponRedemption::withoutTenancy()->where('tenant_id', $event->tenantId)->whereIn('source', ['referral', 'affiliate'])->exists()) {
                CouponRedemption::withoutTenancy()->create([
                    'tenant_id' => $event->tenantId,
                    'source' => 'referral',
                    'code' => 'دعوة',
                    'kind' => 'percent',
                    'value' => (int) $welcome['percent'],
                    'months_left' => max(1, (int) ($welcome['months'] ?? 1)),
                    'created_at' => now(),
                ]);
            }
        });
    }
}
