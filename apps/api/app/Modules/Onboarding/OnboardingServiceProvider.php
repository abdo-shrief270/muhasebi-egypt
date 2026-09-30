<?php

declare(strict_types=1);

namespace App\Modules\Onboarding;

use App\Modules\Onboarding\Contracts\SetupProgress;
use App\Modules\Onboarding\Contracts\ShopActivity;
use App\Modules\Onboarding\Support\SetupChecklist;
use App\Modules\Onboarding\Support\ShopActivityQuery;
use App\Support\Modules\ModuleServiceProvider;

final class OnboardingServiceProvider extends ModuleServiceProvider
{
    public function register(): void
    {
        $this->app->bind(SetupProgress::class, SetupChecklist::class);
        $this->app->bind(ShopActivity::class, ShopActivityQuery::class);
    }
}
