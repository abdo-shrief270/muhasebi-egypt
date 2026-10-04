<?php

declare(strict_types=1);

namespace App\Modules\Imports;

use App\Modules\Imports\Console\AlertLateShipmentsCommand;
use App\Support\Modules\ModuleServiceProvider;

final class ImportsServiceProvider extends ModuleServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        if ($this->app->runningInConsole()) {
            $this->commands([AlertLateShipmentsCommand::class]);
        }
    }
}
