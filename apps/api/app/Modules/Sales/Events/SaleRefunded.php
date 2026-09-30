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
        /**
         * Units that came back damaged (not restocked), for the returns bin.
         *
         * @var list<array{variant_id: string, qty: int, unit_cost: int, serials: list<string>|null, reason: string|null}>
         */
        public readonly array $damaged = [],
        public readonly ?string $returnReference = null,
        public readonly ?string $saleReference = null,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
