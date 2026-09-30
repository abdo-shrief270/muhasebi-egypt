<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Listeners;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\UsedDevices\Actions\EraseSellerAction;
use App\Modules\UsedDevices\Models\UsedDeviceSeller;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use App\Support\Privacy\PhoneFingerprint;

/**
 * A customer erased by the shop who also sold it devices (same phone) is erased as a seller too:
 * name and phone go; the national ID and card photos wait out the retention period.
 */
final class EraseSellerWithCustomer extends ModuleListener
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
        assert($event instanceof CustomerErased);
        if ($event->phoneFingerprint === null) {
            return;
        }

        $action = app(EraseSellerAction::class);
        PhoneFingerprint::where(UsedDeviceSeller::query()->whereNull('erased_at')->whereNotNull('phone'), 'phone', $event->phoneFingerprint)
            ->get()
            ->each(fn (UsedDeviceSeller $seller) => $action->handle($seller, EraseSellerAction::BY_CUSTOMER_ERASURE));
    }
}
