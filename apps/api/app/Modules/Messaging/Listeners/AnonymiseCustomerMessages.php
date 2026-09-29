<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Listeners;

use App\Modules\Customers\Events\CustomerErased;
use App\Modules\Messaging\Models\MessageLog;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use App\Support\Privacy\PhoneFingerprint;

/** The message log keeps that a message was opened, but not an erased customer's phone. */
final class AnonymiseCustomerMessages extends ModuleListener
{
    protected function module(): string
    {
        return 'messaging';
    }

    protected function evenWhenModuleDisabled(): bool
    {
        return true;
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof CustomerErased);

        // A query update: the log is append-only for the app (the model refuses updates), not for erasure.
        MessageLog::query()
            ->whereNotNull('phone')
            ->where(function ($q) use ($event): void {
                $q->where(fn ($s) => $s->where('subject_type', 'customer')->where('subject_id', $event->customerId));
                if ($event->phoneFingerprint !== null) {
                    $q->orWhere(fn ($p) => PhoneFingerprint::where($p, 'phone', $event->phoneFingerprint));
                }
            })
            ->update(['phone' => null]);
    }
}
