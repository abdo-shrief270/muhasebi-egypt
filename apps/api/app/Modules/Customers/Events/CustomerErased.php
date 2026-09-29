<?php

declare(strict_types=1);

namespace App\Modules\Customers\Events;

use App\Support\Events\DomainEvent;

/**
 * A customer's personal data was erased (anonymised); their financial records stay. Modules that
 * keep copies of the name / phone anonymise their own rows: those of this customer, and those with
 * a phone matching $phoneFingerprint (Support\Privacy\PhoneFingerprint — the number itself is
 * never put in the outbox).
 */
final class CustomerErased extends DomainEvent
{
    public const NAME = 'customers.erased';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $customerId,
        public readonly ?string $phoneFingerprint,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
