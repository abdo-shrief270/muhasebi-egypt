<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Listeners;

use App\Modules\MultiBranch\Events\TransferShipped;
use App\Modules\Notifications\Support\Notifier;
use App\Support\Events\DomainEvent;
use App\Support\Events\ModuleListener;

/** Goods left a branch for another: whoever handles transfers knows to expect them. */
final class NotifyTransfers extends ModuleListener
{
    protected function module(): string
    {
        return 'notifications';
    }

    protected function react(DomainEvent $event): void
    {
        assert($event instanceof TransferShipped);

        app(Notifier::class)->notify(
            'transfer.shipped',
            "تحويل {$event->reference} في الطريق لـ «{$event->toBranch}»",
            "{$event->units} قطعة من «{$event->fromBranch}». استلمها لما توصل.",
            'i-lucide-git-compare-arrows',
            "/transfers/{$event->transferId}",
            'transfers.manage',
        );
    }
}
