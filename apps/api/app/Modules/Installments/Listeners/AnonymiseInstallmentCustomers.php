<?php

declare(strict_types=1);

namespace App\Modules\Installments\Listeners;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Installments\Models\InstallmentPlan;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use App\Support\Privacy\Anonymised;

/**
 * An erased customer's plans (all settled or cancelled: erasure refuses a balance) lose the
 * customer's name and phone, and the guarantor's. The amounts and dates stay (the shop's books).
 */
final class AnonymiseInstallmentCustomers extends ModuleListener
{
    protected function module(): string
    {
        return 'installments';
    }

    protected function evenWhenModuleDisabled(): bool
    {
        return true;
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof CustomerErased);
        InstallmentPlan::query()->where('customer_id', $event->customerId)->update([
            'customer_name' => Anonymised::NAME,
            'customer_phone' => null,
            'guarantor_name' => null,
            'guarantor_phone' => null,
            'notes' => null,
        ]);
    }
}
