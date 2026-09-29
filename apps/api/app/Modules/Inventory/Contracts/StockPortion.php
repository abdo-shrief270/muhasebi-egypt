<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

/**
 * Part of an issue taken from one lot (or from no lot when stock ran below zero).
 */
final readonly class StockPortion
{
    public function __construct(
        public ?string $lotId,
        public int $qty,
        public int $unitCost,
    ) {}
}
