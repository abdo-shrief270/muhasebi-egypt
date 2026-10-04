<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Console;

use App\Modules\OnlineStore\Models\OnlineStore;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('online-store:domains')]
#[Description('The shops\' verified own domains of open stores, one per line (for the nginx certificate sync)')]
final class ListDomainsCommand extends Command
{
    public function handle(): int
    {
        OnlineStore::withoutTenancy()
            ->whereNotNull('custom_domain_verified_at')
            ->where('mode', '!=', 'off')
            ->orderBy('custom_domain')
            ->pluck('custom_domain')
            ->each(fn (string $domain) => $this->line($domain));

        return self::SUCCESS;
    }
}
