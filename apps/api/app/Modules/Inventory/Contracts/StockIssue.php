<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

/**
 * The result of taking stock out: which lots it came from and what it cost (piasters).
 */
final readonly class StockIssue
{
    /**
     * @param  list<StockPortion>  $portions
     */
    public function __construct(
        public array $portions,
        public int $balanceAfter,
    ) {}

    public function totalCost(): int
    {
        return array_sum(array_map(fn (StockPortion $p): int => $p->qty * $p->unitCost, $this->portions));
    }

    /** Average cost per unit of this issue. */
    public function unitCost(): int
    {
        $qty = array_sum(array_map(fn (StockPortion $p): int => $p->qty, $this->portions));

        return $qty > 0 ? intdiv($this->totalCost(), $qty) : 0;
    }
}
