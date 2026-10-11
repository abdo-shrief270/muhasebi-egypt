<?php

declare(strict_types=1);

namespace App\Modules\Billing\Listeners;

use App\Modules\Billing\Support\Subscriptions;
use App\Modules\Identity\Events\TenantRegistered;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/** What the new shop said it does: kept on its subscription for the plan the billing page suggests. */
final class RememberShopNeeds extends ModuleListener
{
    public function __construct(private readonly Subscriptions $subscriptions) {}

    protected function module(): string
    {
        return 'billing';
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof TenantRegistered);
        if ($event->needs === null) {
            return;
        }
        $this->subscriptions->for($event->tenantId)->update(['needs' => $event->needs]);
    }
}
