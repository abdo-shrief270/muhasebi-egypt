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
 */
#[Fillable(['tenant_id', 'plan', 'cycle', 'modules', 'on_trial', 'paid_until', 'beta_until', 'suspended_at', 'suspended_reason'])]
final class Subscription extends Model
{
    use BelongsToTenant, HasUuids;

    protected $attributes = ['modules' => '[]', 'on_trial' => true];

    protected function casts(): array
    {
        return [
            'modules' => 'array',
            'on_trial' => 'boolean',
            'paid_until' => 'datetime',
            'beta_until' => 'datetime',
            'suspended_at' => 'datetime',
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
