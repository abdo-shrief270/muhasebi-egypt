<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Jobs;

use App\Modules\Marketplace\Support\MarketIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** A shop's offers again after its marketplace settings changed (queued, once at a time per shop). */
final class ReindexShop implements ShouldBeUnique, ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(public readonly string $tenantId) {}

    public function uniqueId(): string
    {
        return $this->tenantId;
    }

    public function handle(MarketIndexer $indexer): void
    {
        if ($indexer->enabled()) {
            $indexer->reindexShop($this->tenantId);
        }
    }
}
