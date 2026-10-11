<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Modules\Billing\Support\SubscriptionStatus;
use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property string $id
 * @property string $tenant_id
 * @property string|null $plan
 * @property string|null $cycle
 * @property list<string> $modules
 * @property bool $on_trial
 * @property Carbon $paid_until
 * @property Carbon|null $beta_until end of a free beta period (an admin's grant)
 * @property Carbon|null $suspended_at
 * @property string|null $suspended_reason
 * @property int $credit_balance piasters, taken off the next payment
 * @property int $points_balance
 * @property string|null $referred_by the shop whose code it registered with
 * @property Carbon|null $referral_rewarded_at when that shop got its points
 * @property int|null $lifetime_number «مدى الحياة»: its number (null = not bought)
 * @property Carbon|null $lifetime_since
 * @property string|null $lifetime_plan
 * @property int|null $lifetime_price the price it locked, with its modules (piasters)
 * @property int $lifetime_paid of that price, paid so far
 * @property int|null $lifetime_installments
 * @property int|null $lifetime_maintenance a year's maintenance once the price is paid
 * @property list<string>|null $needs what the owner said the shop does at registration
 */
#[Fillable(['tenant_id', 'plan', 'cycle', 'modules', 'on_trial', 'paid_until', 'beta_until', 'suspended_at', 'suspended_reason', 'credit_balance', 'points_balance', 'referred_by', 'referral_rewarded_at',
    'lifetime_number', 'lifetime_since', 'lifetime_plan', 'lifetime_price', 'lifetime_paid', 'lifetime_installments', 'lifetime_maintenance', 'needs'])]
final class Subscription extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = ['modules' => '[]', 'on_trial' => true, 'credit_balance' => 0, 'points_balance' => 0, 'lifetime_paid' => 0];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'on_trial' => 'boolean',
            'paid_until' => 'datetime',
            'beta_until' => 'datetime',
            'suspended_at' => 'datetime',
            'credit_balance' => 'integer',
            'points_balance' => 'integer',
            'referral_rewarded_at' => 'datetime',
            'lifetime_number' => 'integer',
            'lifetime_since' => 'datetime',
            'lifetime_price' => 'integer',
            'lifetime_paid' => 'integer',
            'lifetime_installments' => 'integer',
            'lifetime_maintenance' => 'integer',
            'needs' => 'array',
        ];
    }

    /** In a free beta period right now. */
    public function inBeta(): bool
    {
        return $this->beta_until !== null && $this->beta_until->isFuture();
    }

    public function status(?Carbon $now = null): SubscriptionStatus
    {
        return SubscriptionStatus::of($this, $now ?? now());
    }

    /**
     * The same rule as SubscriptionStatus::of(), in SQL.
     *
     * @param  Builder<Subscription>  $query
     */
    public function scopeWithStatus(Builder $query, SubscriptionStatus $status): void
    {
        $now = now();
        $grace = $now->copy()->subDays((int) config('billing.grace_days'));
        $suspend = $now->copy()->subDays((int) config('billing.suspend_after_days'));

        if ($status === SubscriptionStatus::Suspended) {
            $query->where(fn (Builder $q) => $q->whereNotNull('suspended_at')->orWhere('paid_until', '<=', $suspend));

            return;
        }
        $query->whereNull('suspended_at');
        match ($status) {
            SubscriptionStatus::Trialing => $query->where('on_trial', true)->where('paid_until', '>', $now),
            SubscriptionStatus::Active => $query->where('on_trial', false)->where('paid_until', '>', $now),
            SubscriptionStatus::PastDue => $query->where('paid_until', '<=', $now)->where('paid_until', '>', $grace),
            SubscriptionStatus::Restricted => $query->where('paid_until', '<=', $grace)->where('paid_until', '>', $suspend),
        };
    }
}
