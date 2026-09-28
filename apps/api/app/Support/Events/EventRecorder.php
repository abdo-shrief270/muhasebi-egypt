<?php

declare(strict_types=1);

namespace App\Support\Events;

use Illuminate\Support\Facades\DB;

/**
 * Writes domain events to the outbox. Call it inside the same DB transaction as the change:
 * if the transaction rolls back, the event never existed.
 */
final class EventRecorder
{
    /** @var list<StoredEvent> */
    private array $recorded = [];

    public function record(DomainEvent $event): StoredEvent
    {
        $stored = StoredEvent::create([
            'tenant_id' => $event->tenantId(),
            'name' => $event::NAME,
            'version' => $event::VERSION,
            'event_class' => $event::class,
            'payload' => $event->toPayload(),
            'occurred_at' => now(),
            'attempts' => 0,
        ]);

        $this->recorded[] = $stored;

        // Publish as soon as the surrounding transaction commits; the scheduled relay is the safety net.
        DB::afterCommit(fn () => PublishDomainEvents::dispatch());

        return $stored;
    }

    /**
     * Events recorded in this process (used by tests).
     *
     * @return list<StoredEvent>
     */
    public function recorded(): array
    {
        return $this->recorded;
    }
}
