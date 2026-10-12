<?php

declare(strict_types=1);

namespace App\Modules\Marketplace;

use App\Modules\Marketplace\Console\PriceRefsCommand;
use App\Modules\Marketplace\Console\ReindexMarketCommand;
use App\Modules\Marketplace\Console\SyncMarketCommand;
use App\Modules\Marketplace\Contracts\MarketModeration;
use App\Modules\Marketplace\Contracts\PhoneCatalog;
use App\Modules\Marketplace\Support\Moderation;
use App\Modules\Marketplace\Support\Phones;
use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class MarketplaceServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(PhoneCatalog::class, Phones::class);
        $this->app->bind(MarketModeration::class, Moderation::class);
    }

    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([SyncMarketCommand::class, ReindexMarketCommand::class, PriceRefsCommand::class]);
        }

        // The market site's server forwards each visitor's IP; this only stops floods of the read-only API.
        RateLimiter::for('market', fn (Request $request) => Limit::perMinute(300)->by('market:'.$request->ip()));
        // Reports and repair bookings from the market: a few per visitor.
        RateLimiter::for('market-reports', fn (Request $request) => Limit::perHour(10)->by('market-reports:'.$request->ip()));
    }
}
