<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;

/** Queued every minute by the scheduler: when a worker runs it, the queue is alive. */
final class QueueHeartbeat implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    public function handle(): void
    {
        Health::beat('queue');
    }
}
