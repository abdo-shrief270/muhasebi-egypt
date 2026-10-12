<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Console;

use App\Modules\Marketplace\Support\MarketIndexer;
use App\Modules\Marketplace\Support\PriceChecks;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('market:price-refs')]
#[Description('Work out the usual price of every catalog phone across shops (to hold back mistyped prices)')]
final class PriceRefsCommand extends Command
{
    public function handle(MarketIndexer $indexer, PriceChecks $prices): int
    {
        if (! $indexer->enabled()) {
            return self::SUCCESS;
        }
        $this->line($prices->refresh().' phone price(s).');

        return self::SUCCESS;
    }
}
