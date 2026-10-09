<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A shop that registered with a partner's link (one partner per shop).
 *
 * @property int $id
 * @property string $affiliate_id
 * @property string $tenant_id
 * @property Carbon|null $first_paid_at
 * @property Carbon|null $commission_until payments up to here earn the partner a share
 * @property Carbon $created_at
 */
#[Fillable(['affiliate_id', 'tenant_id', 'first_paid_at', 'commission_until'])]
final class AffiliateReferral extends Model
{
    protected function casts(): array
    {
        return ['first_paid_at' => 'datetime', 'commission_until' => 'datetime'];
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
