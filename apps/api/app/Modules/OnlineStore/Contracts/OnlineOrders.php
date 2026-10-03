<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Contracts;

/**
 * For Sales: an online order becoming an invoice at the POS («حوّل لفاتورة»).
 */
interface OnlineOrders
{
    /** The open, not yet invoiced order of the current shop; refuses otherwise (DomainRuleException). */
    public function forSale(string $orderId): OrderForSale;

    /**
     * Closes the order with its invoice, inside the sale's transaction; with $feeCollected the
     * delivery fee goes into the cashier's drawer as cash.
     */
    public function invoiced(string $orderId, string $saleId, string $saleReference, string $branchId, bool $feeCollected): void;
}
