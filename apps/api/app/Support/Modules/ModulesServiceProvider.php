<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;

/**
 * Discovers app/Modules/*: registers each module's provider, migrations and routes.
 */
final class ModulesServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ModuleRegistry::class, fn (): ModuleRegistry => ModuleRegistry::discover(app_path('Modules')));

        foreach ($this->app->make(ModuleRegistry::class)->all() as $module) {
            if ($module->provider !== null) {
                $this->app->register($module->provider);
            }
        }
    }

    public function boot(): void
    {
        foreach (glob(app_path('Modules/*'), GLOB_ONLYDIR) ?: [] as $path) {
            if (is_dir($path.'/Database/migrations')) {
                $this->loadMigrationsFrom($path.'/Database/migrations');
            }

            if (is_file($path.'/routes.php') && ! $this->app->routesAreCached()) {
                Route::prefix('api/v1')->middleware('api')->group($path.'/routes.php');
            }
        }
    }
}
