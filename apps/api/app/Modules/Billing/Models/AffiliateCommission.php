<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A partner's share of one real payment. Held until available_at (the shop could still ask for its
 * money back), then it can be paid out; an admin may void it.
 *
 * @property string $id
 * @property string $affiliate_id
 * @property string $tenant_id
 * @property string $invoice_id
 * @property string $invoice_reference
 * @property int $base piasters
 * @property int $rate_bp
 * @property int $amount piasters
 * @property string $status pending | paid | void
 * @property Carbon $available_at
 * @property string|null $payout_id
 * @property string|null $void_reason
 * @property Carbon $created_at
 */
#[Fillable(['affiliate_id', 'tenant_id', 'invoice_id', 'invoice_reference', 'base', 'rate_bp', 'amount', 'status', 'available_at', 'payout_id', 'void_reason'])]
final class AffiliateCommission extends Model
{
    use HasUuids;

    protected function casts(): array
    {
        return ['base' => 'integer', 'rate_bp' => 'integer', 'amount' => 'integer', 'available_at' => 'datetime'];
    }

    /** pending → «متعلّق» until available_at, then «متاح للسحب»; in a payout request → «في طلب سحب». */
    public function state(): string
    {
        return match (true) {
            $this->status !== 'pending' => $this->status,
            $this->payout_id !== null => 'requested',
            $this->available_at->isFuture() => 'held',
            default => 'available',
        };
    }

    /** @return BelongsTo<Affiliate, $this> */
    public function affiliate(): BelongsTo
    {
        return $this->belongsTo(Affiliate::class);
    }
}
