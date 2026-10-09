<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Search\MarketIndices;
use App\Support\Search\SearchClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('search:setup {--recreate : a new empty index for each alias (the old one is dropped)}')]
#[Description('Create the marketplace search indices ({prefix}{name}_v{VERSION}_{time}) behind their aliases, when this version has none yet')]
final class SearchSetup extends Command
{
    public function handle(SearchClient $search): int
    {
        if (! $search->enabled()) {
            $this->error('SEARCH_URL مش متحط في .env');

            return self::FAILURE;
        }
        foreach (MarketIndices::all() as $logical => $definition) {
            $alias = $search->name($logical);
            $version = $alias.'_v'.MarketIndices::VERSION;
            $targets = $search->aliasTargets($alias);
            // This version's index (or a nightly rebuild of it: {alias}_v{N}_{time}) is already live.
            $current = collect($targets)->first(fn (string $t) => $t === $version || str_starts_with($t, $version.'_'));
            if ($current !== null && ! $this->option('recreate')) {
                $this->line("✓ {$alias} → {$current}");

                continue;
            }
            $index = $version.'_'.now()->format('YmdHis');
            $search->createIndex($index, $definition);
            $search->swapAlias($alias, $index);
            foreach ($targets as $old) {
                $search->deleteIndex($old);
            }
            $this->info("+ {$alias} → {$index} (فاضي: شغّل market:reindex)");
        }

        return self::SUCCESS;
    }
}
