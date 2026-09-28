<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/**
 * What other modules may know about a shop.
 */
final readonly class ShopSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public string $code,
        public string $phone,
    ) {}
}
