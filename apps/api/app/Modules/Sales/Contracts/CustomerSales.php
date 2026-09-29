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

    /** When the customer last bought, or null. */
    public function lastSaleAt(string $customerId): ?Carbon;
}
