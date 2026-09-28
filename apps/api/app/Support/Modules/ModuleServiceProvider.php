<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

/**
 * Base provider for a module: registers its event listeners.
 * Routes and migrations are loaded for every module by ModulesServiceProvider.
 */
abstract class ModuleServiceProvider extends ServiceProvider
{
    /** @var array<class-string, list<class-string>> */
    protected array $listen = [];

    public function boot(): void
    {
        foreach ($this->listen as $event => $listeners) {
            foreach ($listeners as $listener) {
                Event::listen($event, $listener);
            }
        }
    }
}
