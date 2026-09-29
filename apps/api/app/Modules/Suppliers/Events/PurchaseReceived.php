<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Events;

use App\Support\Events\DomainEvent;

/**
 * A supplier invoice was posted. For the cash drawer (what was paid in cash), reports,
 * and cost-increase alerts. Amounts are piasters.
 */
final class PurchaseReceived extends DomainEvent
{
    public const NAME = 'purchases.purchase_received';

    /**
     * @param  list<array{variant_id: string, previous_cost: int, new_cost: int}>  $costIncreases
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $purchaseId,
        public readonly string $branchId,
        public readonly string $supplierId,
        public readonly int $total,
        public readonly int $paid,
        public readonly ?string $paymentMethod,
        public readonly array $costIncreases,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
