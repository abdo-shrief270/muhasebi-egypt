<?php

use App\Modules\Onboarding\OnboardingServiceProvider;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'onboarding',
    name: 'ابدأ من هنا',
    tier: ModuleTier::Core,
    description: 'قايمة خطوات البداية للمحل الجديد، محسوبة من شغل المحل نفسه.',
    provider: OnboardingServiceProvider::class,
    sort: 97,
);
