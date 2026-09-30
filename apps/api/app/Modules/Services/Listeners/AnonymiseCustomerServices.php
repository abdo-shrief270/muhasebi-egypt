<?php

declare(strict_types=1);

namespace App\Modules\Services\Listeners;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Services\Models\ServiceTransaction;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use App\Support\Privacy\Anonymised;
use App\Support\Privacy\PhoneFingerprint;

/**
 * Operations typed with an erased customer's phone lose the phone and the name. The amounts,
 * fees and the operator's reference stay (they are the shop's books).
 */
final class AnonymiseCustomerServices extends ModuleListener
{
    protected function module(): string
    {
        return 'services';
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

        // A query update: the ledger is append-only for the app (the model refuses updates), not for erasure.
        PhoneFingerprint::where(ServiceTransaction::query()->whereNotNull('customer_phone'), 'customer_phone', $event->phoneFingerprint)
            ->update(['customer_name' => Anonymised::NAME, 'customer_phone' => null]);
    }
}
