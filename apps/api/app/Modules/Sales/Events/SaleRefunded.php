<?php

declare(strict_types=1);

namespace App\Modules\Sales\Events;

use App\Support\Events\DomainEvent;

final class SaleRefunded extends DomainEvent
{
    public const NAME = 'sales.sale_refunded';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $saleId,
        public readonly string $returnId,
        public readonly string $branchId,
        public readonly int $amount,
        public readonly string $refundMethod,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
