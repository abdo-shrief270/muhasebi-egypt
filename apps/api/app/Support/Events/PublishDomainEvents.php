<?php

declare(strict_types=1);

namespace App\Support\Events;

use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

final class PublishDomainEvents implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $uniqueFor = 5;

    public function handle(EventRelay $relay): void
    {
        $relay->publishPending();
    }
}
