<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Contracts;

use Illuminate\Support\Carbon;

/** A customer's repair tickets, as the Customers module sees them (erasure, data export, retention). */
interface CustomerRepairs
{
    /** Tickets of the customer's still in the shop (not delivered). */
    public function openCount(string $customerId): int;

    /** When a ticket of theirs was last received or delivered, or null. */
    public function lastActivityAt(string $customerId): ?Carbon;

    /**
     * The customer's tickets for a data export, oldest first, without costs or the unlock code.
     * Money in piasters.
     *
     * @return list<array<string, mixed>>
     */
    public function forCustomer(string $customerId): array;
}
