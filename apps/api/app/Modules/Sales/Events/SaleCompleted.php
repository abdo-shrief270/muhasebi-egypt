<?php

declare(strict_types=1);

namespace App\Modules\Sales\Events;

use App\Support\Events\DomainEvent;

/**
 * For the cash drawer (cash taken), reports and low-stock checks. Amounts are piasters.
 */
final class SaleCompleted extends DomainEvent
{
    public const NAME = 'sales.sale_completed';

    /**
     * @param  array<string, int>  $payments  method => amount
     * @param  list<array{variant_id: string, qty: int}>  $items
     */
    public function __construct(
        public readonly string $tenantId,
        public readonly string $saleId,
        public readonly string $branchId,
        public readonly int $total,
        public readonly int $cost,
        public readonly array $payments,
        public readonly int $change,
        public readonly array $items,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
