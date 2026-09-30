<?php

declare(strict_types=1);

namespace App\Modules\Sales\Contracts;

use Carbon\CarbonInterface;

/** One unit sold on an invoice. $price is what the customer paid for it after the invoice discount (piasters). */
final readonly class UnitSale
{
    public function __construct(
        public string $saleId,
        public string $reference,
        public CarbonInterface $soldAt,
        public int $price,
        public ?string $customerName,
    ) {}
}
