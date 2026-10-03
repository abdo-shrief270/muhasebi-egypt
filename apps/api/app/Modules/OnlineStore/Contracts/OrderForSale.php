<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Contracts;

/** An online order as the POS invoices it: the prices the customer was quoted and the delivery fee. */
final readonly class OrderForSale
{
    /**
     * @param  array<string, int>  $prices  variant id => unit price on the order (piasters)
     */
    public function __construct(
        public string $id,
        public string $reference,
        public array $prices,
        public int $deliveryFee,
    ) {}
}
