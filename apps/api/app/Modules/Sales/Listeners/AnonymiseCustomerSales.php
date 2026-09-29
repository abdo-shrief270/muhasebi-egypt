<?php

declare(strict_types=1);

namespace App\Modules\Sales\Listeners;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Sales\Models\Sale;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use App\Support\Privacy\Anonymised;
use App\Support\Privacy\PhoneFingerprint;

/** An erased customer's name and phone come off their invoices (and off receipts typed with their phone). */
final class AnonymiseCustomerSales extends ModuleListener
{
    protected function module(): string
    {
        return 'sales';
    }

    protected function evenWhenModuleDisabled(): bool
    {
        return true;
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof CustomerErased);

        Sale::query()
            ->where(function ($q) use ($event): void {
                $q->where('customer_id', $event->customerId);
                if ($event->phoneFingerprint !== null) {
                    $q->orWhere(fn ($p) => PhoneFingerprint::where($p->whereNotNull('customer_phone'), 'customer_phone', $event->phoneFingerprint));
                }
            })
            ->update(['customer_name' => Anonymised::NAME, 'customer_phone' => null]);
    }
}
