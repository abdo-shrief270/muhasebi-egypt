<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * A partner (شريك): a person who brings shops with their link and earns a share of what those
 * shops pay. Their own sign-in (Sanctum tokens with the `affiliate` ability); never a shop user.
 *
 * @property string $id
 * @property string $name
 * @property string $phone E.164
 * @property string|null $email
 * @property string $code what their link carries (?aff=CODE)
 * @property string $status active | suspended
 * @property string|null $payout_method instapay | wallet | bank
 * @property string|null $payout_account
 * @property string|null $payout_name
 * @property int|null $rate_bp
 * @property string|null $channel
 * @property int $clicks
 * @property string|null $admin_note
 * @property Carbon|null $last_login_at
 * @property Carbon $created_at
 */
#[Fillable(['name', 'phone', 'email', 'password', 'code', 'status', 'payout_method', 'payout_account', 'payout_name', 'rate_bp', 'channel', 'admin_note', 'last_login_at'])]
#[Hidden(['password'])]
final class Affiliate extends Authenticatable
{
    use HasApiTokens, HasUuids;

    public const PAYOUT_METHODS = ['instapay' => 'InstaPay', 'wallet' => 'محفظة (فودافون كاش وغيرها)', 'bank' => 'تحويل بنكي'];

    protected $attributes = ['status' => 'active', 'clicks' => 0];

    protected function casts(): array
    {
        return [
            'password' => 'hashed',
            'rate_bp' => 'integer',
            'clicks' => 'integer',
            'last_login_at' => 'datetime',
        ];
    }

    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    /** Their share in basis points (their own, else the program's). */
    public function rate(): int
    {
        return $this->rate_bp ?? (int) round((float) config('billing.affiliates.rate_percent') * 100);
    }

    /** @return HasMany<AffiliateReferral, $this> */
    public function referrals(): HasMany
    {
        return $this->hasMany(AffiliateReferral::class);
    }

    /** @return HasMany<AffiliateCommission, $this> */
    public function commissions(): HasMany
    {
        return $this->hasMany(AffiliateCommission::class);
    }

    /** @return HasMany<AffiliatePayout, $this> */
    public function payouts(): HasMany
    {
        return $this->hasMany(AffiliatePayout::class);
    }
}
