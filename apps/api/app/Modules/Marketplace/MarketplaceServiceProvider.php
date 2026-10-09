<?php

declare(strict_types=1);

namespace App\Modules\Marketplace;

use App\Modules\Marketplace\Console\ReindexMarketCommand;
use App\Modules\Marketplace\Console\SyncMarketCommand;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class MarketplaceServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncMarketCommand::class, ReindexMarketCommand::class]);
        }

        // The market site's server forwards each visitor's IP; this only stops floods of the read-only API.
        RateLimiter::for('market', fn (Request $request) => Limit::perMinute(300)->by('market:'.$request->ip()));
    }
}
