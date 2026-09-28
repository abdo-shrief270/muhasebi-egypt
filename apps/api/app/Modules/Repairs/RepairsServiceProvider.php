<?php

declare(strict_types=1);

namespace App\Modules\Repairs;

use App\Modules\ModuleManager\Events\ModuleEnabled;
use App\Modules\Repairs\Listeners\SeedDefaultFaults;
use App\Support\Modules\ModuleServiceProvider;

final class RepairsServiceProvider extends ModuleServiceProvider
{
    protected array $listen = [
        ModuleEnabled::class => [SeedDefaultFaults::class],
    ];
}
