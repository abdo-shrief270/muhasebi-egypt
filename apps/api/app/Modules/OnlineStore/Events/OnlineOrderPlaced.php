<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Events;

use App\Support\Events\DomainEvent;

/** A customer placed an order on the shop's online store (no personal data in the outbox). */
final class OnlineOrderPlaced extends DomainEvent
{
    public const NAME = 'online_store.order_placed';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $orderId,
        public readonly string $reference,
        public readonly int $total,
        public readonly int $items,
        public readonly string $fulfilment,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
