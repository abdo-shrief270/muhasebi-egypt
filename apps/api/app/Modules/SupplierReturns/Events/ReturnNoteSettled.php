<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Events;

use App\Support\Events\DomainEvent;

/**
 * A return note was settled: the source accepted all / part of it (credit, refund or replacement)
 * or refused it. For a partner shop source ($sourceType 'shop') only this shop's side is recorded:
 * this event is the hook for settling with the partner's books later.
 */
final class ReturnNoteSettled extends DomainEvent
{
    public const NAME = 'supplier_returns.return_settled';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $noteId,
        public readonly string $branchId,
        public readonly string $sourceType,
        public readonly string $sourceId,
        public readonly string $status,
        public readonly int $acceptedValue,
        public readonly int $rejectedValue,
        public readonly ?string $resolution,
        public readonly ?string $rejectedAction,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
