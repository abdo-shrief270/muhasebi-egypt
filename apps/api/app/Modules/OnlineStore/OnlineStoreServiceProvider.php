<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore;

use App\Support\Modules\ModuleServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;

final class OnlineStoreServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        // The store's server forwards each customer's IP; this only stops floods of the read-only API.
        RateLimiter::for('store', fn (Request $request) => Limit::perMinute(300)->by('store:'.$request->ip()));
    }
}
