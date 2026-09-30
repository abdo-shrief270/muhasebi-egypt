<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Listeners;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Repairs\Models\RepairTicket;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use App\Support\Privacy\Anonymised;

/**
 * An erased customer's name and phone come off their tickets, with what else points at them: the
 * phone's unlock code. The IMEI (it identifies the device, not the person), the device, faults, money and timeline stay.
 */
final class AnonymiseCustomerTickets extends ModuleListener
{
    protected function module(): string
    {
        return 'repairs';
    }

    protected function evenWhenModuleDisabled(): bool
    {
        return true;
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof CustomerErased);

        RepairTicket::query()
            ->where('customer_id', $event->customerId)
            ->update([
                'customer_name' => Anonymised::NAME,
                'customer_phone' => null,
                'unlock_type' => 'none',
                'unlock_code' => null,
                // The IMEI stays: it identifies the device, not the person (stolen-phone checks, warranty).
            ]);
    }
}
