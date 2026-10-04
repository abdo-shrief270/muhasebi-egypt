<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Events;

use App\Support\Events\DomainEvent;

/** A customer booked a repair on the shop's online store (no personal data in the outbox). */
final class RepairBookingPlaced extends DomainEvent
{
    public const NAME = 'online_store.repair_booked';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $bookingId,
        public readonly string $reference,
        public readonly string $device,
        public readonly ?string $preferredOn,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
