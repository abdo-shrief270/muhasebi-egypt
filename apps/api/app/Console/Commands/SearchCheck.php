<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\Search\MarketIndices;
use App\Support\Search\SearchClient;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Throwable;

#[Signature('search:check {text=جراب ايفون 15 برو a54 : a sample to analyze}')]
#[Description('Check the connection to the search cluster and how the Arabic analyzers split a text')]
final class SearchCheck extends Command
{
    public function handle(SearchClient $search): int
    {
        if (! $search->enabled()) {
            $this->error('SEARCH_URL مش متحط في .env');

            return self::FAILURE;
        }
        try {
            $info = $search->info();
        } catch (Throwable $e) {
            $this->error('مش قادر أوصل للسيرفر: '.$e->getMessage());

            return self::FAILURE;
        }
        $this->line("✓ {$info['name']} — Elasticsearch {$info['version']} — {$info['status']}");

        $alias = $search->name(MarketIndices::OFFERS);
        $targets = $search->aliasTargets($alias);
        if ($targets === []) {
            $this->warn("✗ {$alias} مش موجود: شغّل php artisan search:setup");

            return self::FAILURE;
        }
        $this->line("✓ {$alias} → ".implode(', ', $targets));

        $text = (string) $this->argument('text');
        foreach (['ar_text', 'ar_search'] as $analyzer) {
            $this->line("  {$analyzer}: ".implode(' · ', $search->analyze($alias, $analyzer, $text)));
        }

        return self::SUCCESS;
    }
}
