<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Listeners;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\OnlineStore\Models\OnlineOrder;
use App\Modules\OnlineStore\Models\RepairBooking;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use App\Support\Privacy\Anonymised;
use App\Support\Privacy\PhoneFingerprint;

/**
 * An erased customer's name, phone, address and notes come off their online orders (theirs, or
 * placed with their phone without an account), and off their repair bookings. The items and money stay.
 */
final class AnonymiseCustomerOrders extends ModuleListener
{
    protected function module(): string
    {
        return 'online_store';
    }

    protected function evenWhenModuleDisabled(): bool
    {
        return true;
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof CustomerErased);

        OnlineOrder::query()
            ->where(function ($q) use ($event): void {
                $q->where('customer_id', $event->customerId);
                if ($event->phoneFingerprint !== null) {
                    $q->orWhere(fn ($p) => PhoneFingerprint::where($p, 'customer_phone', $event->phoneFingerprint));
                }
            })
            ->update(['customer_name' => Anonymised::NAME, 'customer_phone' => '', 'address' => null, 'notes' => null]);
        RepairBooking::query()
            ->where(function ($q) use ($event): void {
                $q->where('customer_id', $event->customerId);
                if ($event->phoneFingerprint !== null) {
                    $q->orWhere(fn ($p) => PhoneFingerprint::where($p, 'customer_phone', $event->phoneFingerprint));
                }
            })
            ->update(['customer_name' => Anonymised::NAME, 'customer_phone' => '']);
    }
}
