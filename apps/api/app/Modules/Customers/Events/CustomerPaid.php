<?php

declare(strict_types=1);

namespace App\Modules\Customers\Events;

use App\Support\Events\DomainEvent;

/** A customer paid off (part of) their account. */
final class CustomerPaid extends DomainEvent
{
    public const NAME = 'customers.paid';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $customerId,
        public readonly string $branchId,
        public readonly int $amount,
        public readonly string $paymentMethod,
        /** The module that collected it through CustomerAccounts::collect() (it has counted it already), or null from the customer page. */
        public readonly ?string $source = null,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
