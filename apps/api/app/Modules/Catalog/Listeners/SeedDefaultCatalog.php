<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Listeners;

use App\Modules\Catalog\Support\DefaultCatalog;
use App\Modules\Identity\Events\TenantRegistered;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/**
 * A new shop gets the starter categories, brands and phone models.
 */
final class SeedDefaultCatalog extends ModuleListener
{
    protected function module(): string
    {
        return 'catalog';
    }

    protected function react(DomainEvent $event): void
    {
        if ($event instanceof TenantRegistered) {
            DefaultCatalog::seed($event->tenantId);
        }
    }
}
