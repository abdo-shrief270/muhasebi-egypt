<?php

declare(strict_types=1);

namespace App\Support\Events;

use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Facades\DB;
use Throwable;

/**
 * Publishes unpublished outbox rows to Laravel's event dispatcher, oldest first.
 * Listeners are queued (see ModuleListener), so publishing is cheap.
 */
final class EventRelay
{
    public function __construct(
        private readonly Dispatcher $events,
        private readonly CurrentTenant $tenant,
    ) {}

    public function publishPending(int $batchSize = 100): int
    {
        return DB::transaction(function () use ($batchSize): int {
            $pending = StoredEvent::query()
                ->whereNull('published_at')
                ->orderBy('occurred_at')
                ->orderBy('id')
                ->limit($batchSize)
                ->lock('for update skip locked') // several relays can run safely in parallel (PostgreSQL)
                ->get();

            foreach ($pending as $stored) {
                try {
                    $this->tenant->runAs($stored->tenant_id, fn () => $this->events->dispatch($stored->toDomainEvent()));
                    $stored->forceFill(['published_at' => now()])->save();
                } catch (Throwable $e) {
                    $stored->increment('attempts');
                    report($e);
                }
            }

            return $pending->count();
        });
    }
}
