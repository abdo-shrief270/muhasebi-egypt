<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Events;

use App\Support\Events\DomainEvent;

/**
 * Recorded for the shop that received the partnership request.
 */
final class ShopConnectionRequested extends DomainEvent
{
    public const NAME = 'shop_orders.connection_requested';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $connectionId,
        public readonly string $requesterTenantId,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
