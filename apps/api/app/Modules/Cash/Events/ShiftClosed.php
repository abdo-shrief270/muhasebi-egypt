<?php

declare(strict_types=1);

namespace App\Modules\Cash\Events;

use App\Support\Events\DomainEvent;

/** A cashier's shift was closed; the difference is counted − expected cash (piasters, < 0 = short). */
final class ShiftClosed extends DomainEvent
{
    public const NAME = 'cash.shift_closed';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $shiftId,
        public readonly string $branchId,
        public readonly string $reference,
        public readonly string $userName,
        public readonly int $cashDifference,
        public readonly ?string $closedByName = null,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
