<?php

declare(strict_types=1);

namespace App\Modules\Billing\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A subscription coupon made by the platform admins (not a shop's data: no tenant).
 *
 * @property string $id
 * @property string $code
 * @property string $kind percent | amount | credit
 * @property int $value percent, or piasters
 * @property int $months how many months of subscription a percent covers
 * @property bool $new_shops_only
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $max_redemptions
 * @property int $redemptions
 * @property bool $is_active
 * @property string|null $note
 */
#[Fillable(['code', 'kind', 'value', 'months', 'new_shops_only', 'starts_on', 'ends_on', 'max_redemptions', 'redemptions', 'is_active', 'note'])]
final class BillingCoupon extends Model
{
    use HasUuids;

    public const KINDS = ['percent', 'amount', 'credit'];

    protected $attributes = ['months' => 1, 'redemptions' => 0, 'new_shops_only' => false, 'is_active' => true];

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'months' => 'integer',
            'new_shops_only' => 'boolean',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'max_redemptions' => 'integer',
            'redemptions' => 'integer',
            'is_active' => 'boolean',
        ];
    }

    /** «خصم 20% لمدة 3 شهور» / «خصم 100 ج» / «رصيد 50 ج» */
    public static function describe(string $kind, int $value, int $months): string
    {
        $pounds = fn (int $p) => ($p % 100 === 0 ? number_format($p / 100) : number_format($p / 100, 2)).' ج';

        return match ($kind) {
            'percent' => "خصم {$value}%".($months > 1 ? " لمدة {$months} شهور" : ' على شهر'),
            'amount' => 'خصم '.$pounds($value).' على الدفعة الجاية',
            default => 'رصيد '.$pounds($value).' في حسابك',
        };
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'kind' => $this->kind,
            'value' => $this->value,
            'months' => $this->months,
            'label' => self::describe($this->kind, $this->value, $this->months),
            'new_shops_only' => $this->new_shops_only,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'max_redemptions' => $this->max_redemptions,
            'redemptions' => $this->redemptions,
            'is_active' => $this->is_active,
            'note' => $this->note,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
