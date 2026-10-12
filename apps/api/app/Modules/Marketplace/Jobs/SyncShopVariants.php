<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Jobs;

use App\Modules\Marketplace\Support\MarketIndexer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;

/** Some of a shop's offers again (it linked them to the phone catalog). */
final class SyncShopVariants implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /** @param  list<string>  $variantIds */
    public function __construct(public readonly string $tenantId, public readonly array $variantIds) {}

    public function handle(MarketIndexer $indexer): void
    {
        if ($indexer->enabled()) {
            $indexer->syncVariants($this->tenantId, $this->variantIds);
        }
    }
}
