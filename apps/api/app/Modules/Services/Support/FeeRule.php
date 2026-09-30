<?php

declare(strict_types=1);

namespace App\Modules\Services\Support;

/**
 * A suggested fee: percent of the amount plus a fixed part, rounded up to a step, then kept
 * between min and max. Mirrored by `serviceFee()` in the web app (apps/web/app/utils/services.ts);
 * change both together.
 */
final readonly class FeeRule
{
    public function __construct(
        public int $percent = 0,
        public int $fixed = 0,
        public int $min = 0,
        public ?int $max = null,
        public int $roundTo = 0,
    ) {}

    public static function none(): self
    {
        return new self;
    }

    /** @param  int  $amount  piasters */
    public function feeFor(int $amount): int
    {
        if ($amount <= 0) {
            return 0;
        }
        // The percent part to the nearest piaster (basis points: 150 = 1.5%).
        $fee = intdiv($amount * $this->percent + 5000, 10000) + $this->fixed;
        if ($this->roundTo > 1 && $fee % $this->roundTo !== 0) {
            $fee = (intdiv($fee, $this->roundTo) + 1) * $this->roundTo;
        }
        $fee = max($fee, $this->min);

        return $this->max !== null ? min($fee, $this->max) : $fee;
    }

    /**
     * @return array{percent: int, fixed: int, min: int, max: int|null, round_to: int}
     */
    public function toArray(): array
    {
        return ['percent' => $this->percent, 'fixed' => $this->fixed, 'min' => $this->min, 'max' => $this->max, 'round_to' => $this->roundTo];
    }
}
