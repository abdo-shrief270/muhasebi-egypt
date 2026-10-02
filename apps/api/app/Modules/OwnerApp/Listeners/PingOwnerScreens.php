<?php

declare(strict_types=1);

namespace App\Modules\OwnerApp\Listeners;

use App\Modules\Cash\Events\ShiftClosed;
use App\Modules\OwnerApp\Broadcasting\ShopActivity;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SaleRefunded;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/** Tells the owner's open «النهارده» / «اللي بيحصل» screens that something happened. */
final class PingOwnerScreens extends ModuleListener
{
    protected function module(): string
    {
        return 'owner_app';
    }

    protected function react(DomainEvent $event): void
    {
        $kind = match (true) {
            $event instanceof SaleCompleted => 'sale',
            $event instanceof SaleRefunded => 'return',
            $event instanceof ShiftClosed => 'shift',
            default => 'other',
        };
        broadcast(new ShopActivity((string) $event->tenantId(), $kind));
    }
}
