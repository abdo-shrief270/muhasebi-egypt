<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use App\Support\Tenancy\BelongsToTenant;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A discount the shop holds (a coupon it entered, or the referral welcome) until payments use it up.
 *
 * @property string $id
 * @property string $tenant_id
 * @property string|null $coupon_id
 * @property string $source coupon | referral
 * @property string $code
 * @property string $kind percent | amount
 * @property int $value
 * @property int $months_left
 * @property Carbon $created_at
 * @property Carbon|null $used_up_at
 */
#[Fillable(['tenant_id', 'coupon_id', 'source', 'code', 'kind', 'value', 'months_left', 'created_at', 'used_up_at'])]
final class CouponRedemption extends Model
{
    use BelongsToTenant, HasUuids;

    public const UPDATED_AT = null;

    protected $table = 'billing_coupon_redemptions';

    protected function casts(): array
    {
        return ['value' => 'integer', 'months_left' => 'integer', 'created_at' => 'datetime', 'used_up_at' => 'datetime'];
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'source' => $this->source,
            'code' => $this->code,
            'kind' => $this->kind,
            'value' => $this->value,
            'months_left' => $this->months_left,
            'label' => $this->kind === 'percent'
                ? ($this->source === 'referral' ? 'خصم الدعوة' : 'خصم')." {$this->value}% — فاضل {$this->months_left} ".($this->months_left === 1 ? 'شهر' : 'شهور')
                : BillingCoupon::describe($this->kind, $this->value, 1),
        ];
    }
}
