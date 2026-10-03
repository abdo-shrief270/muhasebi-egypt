<?php

declare(strict_types=1);

namespace App\Modules\MultiBranch\Events;

use App\Support\Events\DomainEvent;

/** Goods left a branch for another: the receiving branch should expect them. */
final class TransferShipped extends DomainEvent
{
    public const NAME = 'multi_branch.transfer_shipped';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $transferId,
        public readonly string $reference,
        public readonly string $fromBranch,
        public readonly string $toBranch,
        public readonly int $units,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
