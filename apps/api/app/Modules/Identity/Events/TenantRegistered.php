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
        /** The shop whose invite code it registered with. */
        public readonly ?string $referredBy = null,
        /** The partner code from the link it registered with (unchecked). */
        public readonly ?string $affiliateCode = null,
    ) {}

    public function tenantId(): string
    {
        return $this->tenantId;
    }
}
