<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Contracts;

use App\Support\Exceptions\DomainRuleException;

/** For Repairs: the intake of a device booked on the online store closes its booking. */
interface RepairBookings
{
    /**
     * Inside the intake's transaction.
     *
     * @throws DomainRuleException booking_not_found (404) / booking_closed
     */
    public function converted(string $bookingId, string $ticketId, string $ticketReference): void;
}
