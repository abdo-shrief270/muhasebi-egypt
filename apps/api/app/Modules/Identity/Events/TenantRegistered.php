<?php

declare(strict_types=1);

namespace App\Modules\Identity\Events;

use App\Support\Events\DomainEvent;

final class TenantRegistered extends DomainEvent
{
    public const NAME = 'identity.tenant_registered';

    public function __construct(
        public readonly string $tenantId,
        public readonly string $shopType,
        public readonly string $ownerId,
        /** @var list<string> all the shop's types; $shopType is the first */
        public readonly array $shopTypes = [],
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
