<?php

declare(strict_types=1);

namespace App\Modules\Sales\Contracts;

use Illuminate\Support\Carbon;

/** A customer's invoices, as the Customers module sees them (data export, retention). */
interface CustomerSales
{
    /**
     * The customer's invoices for a data export, oldest first, without costs. Money in piasters.
     *
     * @return list<array<string, mixed>>
     */
    public function forCustomer(string $customerId): array;

    /**
     * A sale's customer and what of it went on the customer's account (آجل), for the modules that
     * plan how it's paid (installments). Null when there's no such sale in this shop.
     *
     * @return array{customer_id: string|null, reference: string, credit: int, branch_id: string}|null
     */
    public function creditOf(string $saleId): ?array;

    /** When the customer last bought, or null. */
    public function lastSaleAt(string $customerId): ?Carbon;
}
