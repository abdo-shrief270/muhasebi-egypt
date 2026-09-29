<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

/**
 * Why stock moved: the movement type plus the document behind it (a sale, a purchase…).
 */
final readonly class StockReference
{
    public function __construct(
        public MovementType $type,
        public ?string $refType = null,
        public ?string $refId = null,
        public ?string $reason = null,
        public ?string $note = null,
    ) {}
}
