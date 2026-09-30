<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Events;

use App\Support\Events\DomainEvent;

/** A part taken off a device turned out defective: it did not go back to stock (the returns bin takes it). */
final class DefectivePartRemoved extends DomainEvent
{
    public const NAME = 'repairs.defective_part_removed';

    /**
     * @param  list<string>|null  $serials
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $ticketId,
        public readonly string $ticketReference,
        public readonly string $branchId,
        public readonly string $variantId,
        public readonly int $qty,
        public readonly int $unitCost,
        public readonly ?array $serials,
        public readonly ?string $reason,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
