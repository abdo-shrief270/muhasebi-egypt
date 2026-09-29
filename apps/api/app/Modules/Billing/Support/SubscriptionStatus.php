<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\Subscription;
use Illuminate\Support\Carbon;

/**
 * Where a shop's subscription stands, from its dates: on trial / paid → past due (a week, all works)
 * → restricted (selling and daily work go on; no new products, staff or branches) → suspended
 * (read-only). An admin can suspend a shop outright.
 */
enum SubscriptionStatus: string
{
    case Trialing = 'trialing';
    case Active = 'active';
    case PastDue = 'past_due';
    case Restricted = 'restricted';
    case Suspended = 'suspended';

    public static function of(Subscription $subscription, Carbon $now): self
    {
        if ($subscription->suspended_at !== null) {
            return self::Suspended;
        }
        $end = $subscription->paid_until;

        return match (true) {
            $now->lt($end) => $subscription->on_trial ? self::Trialing : self::Active,
            $now->lt($end->copy()->addDays((int) config('billing.grace_days'))) => self::PastDue,
            $now->lt($end->copy()->addDays((int) config('billing.suspend_after_days'))) => self::Restricted,
            default => self::Suspended,
        };
    }

    public function label(): string
    {
        return match ($this) {
            self::Trialing => 'تجربة مجانية',
            self::Active => 'مشترك',
            self::PastDue => 'الاشتراك خلص',
            self::Restricted => 'محدود',
            self::Suspended => 'موقوف',
        };
    }

    public function isPaidUp(): bool
    {
        return $this === self::Trialing || $this === self::Active;
    }
}
