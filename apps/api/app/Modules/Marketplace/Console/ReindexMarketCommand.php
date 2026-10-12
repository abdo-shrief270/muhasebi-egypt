<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Console;

use App\Modules\Marketplace\Support\MarketIndexer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('market:reindex {--shop= : only this shop (tenant id)} {--if-outdated : only when the live index has an older definition (deploys)}')]
#[Description('Rebuild the marketplace index from the shops\' data (a new index, then the alias moves), or one shop\'s offers')]
final class ReindexMarketCommand extends Command
{
    public function handle(MarketIndexer $indexer): int
    {
        if (! $indexer->enabled()) {
            $this->line('SEARCH_URL مش متحط في .env: مفيش حاجة تتعمل.');

            return self::SUCCESS;
        }
        if ($this->option('if-outdated') && ! $indexer->outdated()) {
            $this->line('The marketplace index is up to date.');

            return self::SUCCESS;
        }
        if ($shop = (string) $this->option('shop')) {
            $failed = $indexer->reindexShop($shop);
            $this->info("{$indexer->countFor($shop)} offer(s) for the shop".($failed ? ", {$failed} failed" : '').'.');

            return $failed === 0 ? self::SUCCESS : self::FAILURE;
        }
        $result = $indexer->rebuild();
        $this->info("{$result['index']}: {$result['shops']} shop(s), {$result['offers']} offer(s)".($result['failed'] ? ", {$result['failed']} failed" : '').'.');

        return $result['failed'] === 0 ? self::SUCCESS : self::FAILURE;
    }
}
