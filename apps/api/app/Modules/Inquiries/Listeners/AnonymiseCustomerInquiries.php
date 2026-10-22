<?php

declare(strict_types=1);

namespace App\Modules\Inquiries\Listeners;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Inquiries\Models\Inquiry;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use App\Support\Privacy\Anonymised;

/** An erased customer's name and phone come off their inquiries, and what they asked with them. */
final class AnonymiseCustomerInquiries extends ModuleListener
{
    protected function module(): string
    {
        return 'inquiries';
    }

    protected function evenWhenModuleDisabled(): bool
    {
        return true;
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof CustomerErased);

        Inquiry::query()->where('customer_id', $event->customerId)->update([
            'customer_name' => Anonymised::NAME,
            'customer_phone' => null,
            'text' => '—',
            'outcome' => null,
        ]);
    }
}
