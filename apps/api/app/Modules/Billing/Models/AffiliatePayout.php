<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A partner asking for what they earned: every available commission goes into it; an admin sends
 * the money (and records the reference) or rejects it (the commissions are free again).
 *
 * @property string $id
 * @property string $affiliate_id
 * @property int $amount
 * @property string $method
 * @property string $account
 * @property string|null $account_name
 * @property string $status requested | paid | rejected
 * @property string|null $reference
 * @property string|null $note
 * @property string|null $decided_by_name
 * @property Carbon|null $decided_at
 * @property Carbon $created_at
 */
#[Fillable(['affiliate_id', 'amount', 'method', 'account', 'account_name', 'status', 'reference', 'note', 'decided_by_name', 'decided_at'])]
final class AffiliatePayout extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['amount' => 'integer', 'decided_at' => 'datetime'];
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }

    /** @return HasMany<AffiliateCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class, 'payout_id');
    }
}
