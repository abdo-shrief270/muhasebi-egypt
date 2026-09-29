<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Events;

use App\Support\Events\DomainEvent;

/**
 * Money went to a supplier (on its own, not as part of an invoice). For the cash drawer and reports.
 */
final class SupplierPaid extends DomainEvent
{
    public const NAME = 'purchases.supplier_paid';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $supplierId,
        public readonly string $branchId,
        public readonly int $amount,
        public readonly string $paymentMethod,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
