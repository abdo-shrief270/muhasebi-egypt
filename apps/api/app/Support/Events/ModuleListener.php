<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Support\Modules\ModuleAccess;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\DB;

/**
 * Base class for reacting to another module's events.
 *  - runs in the event's tenant context
 *  - skips tenants that don't have the listening module enabled
 *  - runs at most once per (event, listener), so redelivery is harmless
 */
abstract class ModuleListener implements ShouldQueue
{
    public int $tries = 5;

    /** @var list<int> */
    public array $backoff = [5, 30, 120, 600];

    public bool $afterCommit = true;

    /** Key of the module this listener belongs to. */
    abstract protected function module(): string;

    abstract protected function react(DomainEvent $event): void;

    /** Narrow which events of the listened type matter (e.g. only one module key). */
    protected function shouldReact(DomainEvent $event): bool
    {
        return true;
    }

    public function handle(DomainEvent $event): void
    {
        app(CurrentTenant::class)->runAs($event->tenantId(), function () use ($event): void {
            if (! $this->shouldReact($event)) {
                return;
            }

            if ($event->tenantId() !== null && ! app(ModuleAccess::class)->enabled($this->module(), $event->tenantId())) {
                return;
            }

            DB::transaction(function () use ($event): void {
                $claimed = DB::table('processed_events')->insertOrIgnore([
                    'event_id' => $event->eventId(),
                    'listener' => static::class,
                    'processed_at' => now(),
                ]);

                if ($claimed === 0) {
                    return;
                }

                $this->react($event);
            });
        });
    }
}
