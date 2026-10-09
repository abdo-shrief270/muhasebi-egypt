<?php

declare(strict_types=1);

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Support\Affiliates;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Identity\Events\TenantRegistered;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/** A shop registered from a partner's link: the partner is remembered for their share of its payments. */
final class AttributeAffiliateSignup extends ModuleListener
{
    public function __construct(
        private readonly Affiliates $affiliates,
        private readonly ShopDirectory $shops,
    ) {}

    protected function module(): string
    {
        return 'billing';
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof TenantRegistered);
        if ($event->affiliateCode === null || $event->affiliateCode === '') {
            return;
        }
        $this->affiliates->attribute($event->tenantId, $event->affiliateCode, $this->shops->find($event->tenantId)?->phone);
    }
}
