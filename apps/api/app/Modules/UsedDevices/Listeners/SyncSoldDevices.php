<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Listeners;

use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Events\SaleRefunded;
use App\Modules\UsedDevices\Models\UsedDevice;
use App\Modules\UsedDevices\Support\DeviceStatus;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/**
 * A used device sold at the POS becomes «اتباع» with its invoice and price (and leaves the POS);
 * brought back into stock by a return, it is for sale again. Also runs for shops that turned the
 * module off, so the devices they still hold stay right.
 */
final class SyncSoldDevices extends ModuleListener
{
    protected function module(): string
    {
        return 'used_devices';
    }

    protected function evenWhenModuleDisabled(): bool
    {
        return true;
    }

    protected function react(DomainEvent $event): void
    {
        $devices = match (true) {
            $event instanceof SaleCompleted => UsedDevice::query()->whereIn('variant_id', array_column($event->items, 'variant_id'))->lockForUpdate()->get(),
            $event instanceof SaleRefunded => UsedDevice::query()->where('sale_id', $event->saleId)->lockForUpdate()->get(),
            default => null,
        };

        if ($devices !== null) {
            app(DeviceStatus::class)->refresh($devices);
        }
    }
}
