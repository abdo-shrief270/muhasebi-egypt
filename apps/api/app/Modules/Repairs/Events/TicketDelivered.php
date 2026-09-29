<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Events;

use App\Support\Events\DomainEvent;

/** A device went back to its owner (repaired or not): for reports and messages. */
final class TicketDelivered extends DomainEvent
{
    public const NAME = 'repairs.ticket_delivered';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $branchId,
        public readonly bool $repaired,
        public readonly int $total,
        public readonly int $partsCost,
        public readonly ?string $technicianId,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
