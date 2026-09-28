<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Events\EventRelay;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('events:relay')]
#[Description('Publish pending domain events from the outbox')]
final class RelayDomainEvents extends Command
{
    public function handle(EventRelay $relay): int
    {
        $count = $relay->publishPending();
        $this->components->info("Published {$count} event(s).");

        return self::SUCCESS;
    }
}
