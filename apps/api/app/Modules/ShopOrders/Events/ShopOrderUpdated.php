<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Events;

use App\Support\Events\DomainEvent;

/**
 * Recorded once per shop involved, so each shop's listeners (notifications, real time,
 * later: purchases for the buyer / sales for the seller) run in that shop's context.
 * $actorTenantId is the shop that made the change; $orderType is goods | repair
 * (both null on events recorded before they were added).
 */
final class ShopOrderUpdated extends DomainEvent
{
    public const NAME = 'shop_orders.order_updated';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $orderId,
        public readonly string $reference,
        public readonly string $party,
        public readonly ?string $fromStatus,
        public readonly string $toStatus,
        public readonly string $counterpartyTenantId,
        public readonly ?int $total,
        public readonly ?string $actorTenantId = null,
        public readonly ?string $orderType = null,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
