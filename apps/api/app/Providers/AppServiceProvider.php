<?php

namespace App\Providers;

use App\Support\Events\EventRecorder;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Scoped: reset between Octane requests and queue jobs.
        $this->app->scoped(CurrentTenant::class);
        $this->app->scoped(CurrentBranch::class);
        $this->app->scoped(EventRecorder::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());
    }
}
