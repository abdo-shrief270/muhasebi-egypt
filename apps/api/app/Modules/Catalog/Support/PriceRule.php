<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support;

/**
 * How a bulk edit computes a new price: start from a price (or the average cost), then add a
 * percent or an amount, or set a fixed price; then round to a step.
 */
final readonly class PriceRule
{
    public const CHANGES = ['percent', 'amount', 'set'];

    public const ROUNDING = ['nearest', 'up', 'down'];

    /**
     * @param  string  $field  the price being changed (a ProductVariant::PRICE_FIELDS entry)
     * @param  string  $base  another price field, or 'cost'
     * @param  int  $value  basis points for percent (1000 = 10%), piasters otherwise
     * @param  int  $step  piasters to round to; 0 = no rounding
     */
    public function __construct(
        public string $field,
        public string $base,
        public string $change,
        public int $value,
        public int $step = 0,
        public string $rounding = 'nearest',
    ) {}

    /** The new price from the base value; null when it can't be computed. */
    public function apply(?int $base): ?int
    {
        if ($this->change === 'set') {
            return $this->round($this->value);
        }
        if ($base === null || $base <= 0) {
            return null;
        }
        $price = $this->change === 'percent'
            ? (int) round($base * (10000 + $this->value) / 10000)
            : $base + $this->value;

        return $this->round($price);
    }

    public function describe(): string
    {
        $base = $this->base === 'cost' ? 'التكلفة' : self::label($this->base);

        return self::label($this->field).': '.match ($this->change) {
            'set' => 'سعر ثابت '.self::money($this->value),
            'percent' => $base.' '.($this->value >= 0 ? '+' : '−').rtrim(rtrim(number_format(abs($this->value) / 100, 2, '.', ''), '0'), '.').'%',
            default => $base.' '.($this->value >= 0 ? '+' : '−').self::money(abs($this->value)),
        };
    }

    public static function label(string $field): string
    {
        return match ($field) {
            'price_retail' => 'سعر القطاعي',
            'price_wholesale' => 'سعر الجملة',
            'price_technician' => 'سعر الفني',
            'price_online' => 'سعر الأونلاين',
            default => $field,
        };
    }

    private static function money(int $piasters): string
    {
        return rtrim(rtrim(number_format($piasters / 100, 2, '.', ''), '0'), '.').' ج';
    }

    private function round(int $price): int
    {
        if ($this->step <= 0) {
            return $price;
        }

        return match ($this->rounding) {
            'up' => (int) (ceil($price / $this->step) * $this->step),
            'down' => (int) (floor($price / $this->step) * $this->step),
            default => (int) (round($price / $this->step) * $this->step),
        };
    }
}
