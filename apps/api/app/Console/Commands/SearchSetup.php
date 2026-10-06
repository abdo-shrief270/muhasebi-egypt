<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Search\MarketIndices;
use App\Support\Search\SearchClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('search:setup {--recreate : drop and rebuild this version\'s indices (empties them)}')]
#[Description('Create the marketplace search indices ({prefix}{name}_v{VERSION}) and point their aliases at them')]
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
            $index = $alias.'_v'.MarketIndices::VERSION;
            if ($this->option('recreate')) {
                $search->deleteIndex($index);
            }
            if ($search->indexExists($index)) {
                $this->line("✓ {$index} موجود");
            } else {
                $search->createIndex($index, $definition);
                $this->info("+ {$index} اتعمل");
            }
            $search->swapAlias($alias, $index);
            $this->line("  {$alias} → {$index}");
        }

        return self::SUCCESS;
    }
}
