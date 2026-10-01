<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\Cash\Events\ShiftClosed;
use App\Modules\Notifications\Support\Notifier;
use App\Modules\Sales\Events\SaleRefunded;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;
use App\Support\Modules\FeatureAccess;

/**
 * What the owner wants to hear about while away (owner app, `owner_app.alerts`): a shift closed
 * short or over by more than the owner's limit, and every sales return.
 */
final class NotifyOwner extends ModuleListener
{
    public function __construct(
        private readonly Notifier $notifier,
        private readonly FeatureAccess $features,
    ) {}

    protected function module(): string
    {
        return 'owner_app';
    }

    protected function react(DomainEvent $event): void
    {
        if ($event instanceof ShiftClosed) {
            $limit = (int) ($this->features->setting('owner_app.cash_alert') ?? 50) * 100;
            if (! $this->features->enabled('owner_app.cash_alert') || abs($event->cashDifference) < max(1, $limit)) {
                return;
            }
            $what = $event->cashDifference < 0 ? 'عجز' : 'زيادة';
            $this->notifier->notify(
                'owner.cash_difference',
                "{$what} ".number_format(abs($event->cashDifference) / 100, 2)." ج في وردية {$event->userName}",
                "{$event->reference}".($event->closedByName ? " — قفلها {$event->closedByName}" : ''),
                $event->cashDifference < 0 ? 'i-lucide-triangle-alert' : 'i-lucide-wallet',
                "/cash/{$event->shiftId}",
                'owner_app.alerts',
            );

            return;
        }

        if ($event instanceof SaleRefunded && $this->features->enabled('owner_app.refund_alert')) {
            $this->notifier->notify(
                'owner.refund',
                'مرتجع '.number_format($event->amount / 100, 2).' ج'.($event->saleReference ? " من {$event->saleReference}" : ''),
                $event->returnReference,
                'i-lucide-undo-2',
                "/sales/{$event->saleId}",
                'owner_app.alerts',
            );
        }
    }
}
