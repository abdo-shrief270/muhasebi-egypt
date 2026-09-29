<?php

declare(strict_types=1);

namespace App\Modules\Cash;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Support\Modules\ModuleServiceProvider;

final class CashServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(CashDrawer::class, CashDrawerService::class);
    }
}
