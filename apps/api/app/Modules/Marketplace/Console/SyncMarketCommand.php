<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Console;

use App\Modules\Catalog\Contracts\MarketCatalog;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Marketplace\Support\MarketIndexer;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Throwable;

#[Signature('market:sync')]
#[Description('Bring the marketplace index up to date with the products, prices and stock that changed since the last run')]
final class SyncMarketCommand extends Command
{
    private const MARK = 'offers';

    public function handle(MarketIndexer $indexer, MarketCatalog $catalog, StockLedger $stock): int
    {
        if (! $indexer->enabled()) {
            return self::SUCCESS;
        }
        $started = Carbon::now();
        $mark = DB::table('market_sync_marks')->where('name', self::MARK)->value('at');
        // A few seconds of overlap: a change committed while the last run read is never missed.
        $since = ($mark === null ? $started->copy()->subMinutes(10) : Carbon::parse($mark))->subSeconds(5);

        $changed = $catalog->changedSince($since);
        foreach ($stock->changedSince($since) as $tenantId => $ids) {
            $changed[$tenantId] = [...($changed[$tenantId] ?? []), ...$ids];
        }

        $failed = 0;
        foreach ($changed as $tenantId => $ids) {
            try {
                $failed += $indexer->syncVariants($tenantId, $ids);
            } catch (Throwable $e) {
                // One shop (or a moment the cluster is down) must not hold back the rest; the nightly rebuild catches up.
                report($e);
                $failed += count($ids);
            }
        }

        DB::table('market_sync_marks')->upsert([['name' => self::MARK, 'at' => $started]], ['name'], ['at']);
        $this->line(count($changed).' shop(s) synced'.($failed > 0 ? ", {$failed} failed" : '').'.');

        return self::SUCCESS;
    }
}
