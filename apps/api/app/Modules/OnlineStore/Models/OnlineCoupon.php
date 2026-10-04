<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Models;

use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\BelongsToTenant;
use App\Support\Time\ShopDay;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * A discount code for the online store: a percent (with an optional ceiling) or a fixed amount
 * off the goods, from a minimum order, between two dates, a number of times, once per phone.
 *
 * @property string $id
 * @property string $code
 * @property string $kind percent | amount
 * @property int $value percent (1–90) or piasters
 * @property int $min_order
 * @property int|null $max_discount
 * @property Carbon|null $starts_on
 * @property Carbon|null $ends_on
 * @property int|null $max_uses
 * @property int $uses
 * @property bool $once_per_phone
 * @property bool $is_active
 */
#[Fillable(['tenant_id', 'code', 'kind', 'value', 'min_order', 'max_discount', 'starts_on', 'ends_on', 'max_uses', 'uses', 'once_per_phone', 'is_active'])]
final class OnlineCoupon extends Model
{
    use BelongsToTenant, HasUuids;

    protected function casts(): array
    {
        return [
            'value' => 'integer',
            'min_order' => 'integer',
            'max_discount' => 'integer',
            'starts_on' => 'date',
            'ends_on' => 'date',
            'max_uses' => 'integer',
            'uses' => 'integer',
            'once_per_phone' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    public static function normalize(string $code): string
    {
        return mb_strtoupper(trim($code));
    }

    /**
     * Active, in its dates and not used up today — what the store's cart offers a code field for.
     *
     * @param  Builder<self>  $query
     */
    public function scopeUsable(Builder $query): void
    {
        $today = ShopDay::today()->toDateString();
        $query->where('is_active', true)
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhere('starts_on', '<=', $today))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhere('ends_on', '>=', $today))
            ->where(fn ($q) => $q->whereNull('max_uses')->orWhereColumn('uses', '<', 'max_uses'));
    }

    /**
     * The discount on these goods, or why it doesn't apply (DomainRuleException, shown to the
     * customer). The phone check is done by the order.
     */
    public function discountFor(int $subtotal): int
    {
        $today = ShopDay::today();
        if (! $this->is_active) {
            throw new DomainRuleException('الكود ده مش شغال.', 'coupon_invalid');
        }
        if ($this->starts_on !== null && $this->starts_on->gt($today)) {
            throw new DomainRuleException('الكود ده لسه ما بدأش (من '.$this->starts_on->format('d/m').').', 'coupon_not_started');
        }
        if ($this->ends_on !== null && $this->ends_on->lt($today)) {
            throw new DomainRuleException('الكود ده انتهى.', 'coupon_expired');
        }
        if ($this->max_uses !== null && $this->uses >= $this->max_uses) {
            throw new DomainRuleException('الكود ده خلص.', 'coupon_used_up');
        }
        if ($subtotal < $this->min_order) {
            throw new DomainRuleException('الكود ده لطلب من '.number_format($this->min_order / 100, 2).' ج أو أكتر.', 'coupon_min_order', context: ['min_order' => $this->min_order]);
        }
        $discount = $this->kind === 'percent' ? intdiv($subtotal * $this->value, 100) : $this->value;
        if ($this->max_discount !== null) {
            $discount = min($discount, $this->max_discount);
        }

        return min($discount, $subtotal);
    }

    /** «10%» / «50 ج» (+ ceiling), for the customer. */
    public function label(): string
    {
        $value = $this->kind === 'percent' ? "{$this->value}%" : self::pounds($this->value).' ج';

        return "خصم {$value}".($this->kind === 'percent' && $this->max_discount !== null ? ' (لحد '.self::pounds($this->max_discount).' ج)' : '');
    }

    private static function pounds(int $piasters): string
    {
        return $piasters % 100 === 0 ? number_format($piasters / 100) : number_format($piasters / 100, 2);
    }

    /** @return array<string, mixed> */
    public function toApi(): array
    {
        return [
            'id' => $this->id,
            'code' => $this->code,
            'kind' => $this->kind,
            'value' => $this->value,
            'label' => $this->label(),
            'min_order' => $this->min_order,
            'max_discount' => $this->max_discount,
            'starts_on' => $this->starts_on?->toDateString(),
            'ends_on' => $this->ends_on?->toDateString(),
            'max_uses' => $this->max_uses,
            'uses' => $this->uses,
            'once_per_phone' => $this->once_per_phone,
            'is_active' => $this->is_active,
        ];
    }
}
