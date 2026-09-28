<?php

use App\Modules\Identity\IdentityServiceProvider;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'identity',
    name: 'الحساب والمستخدمين',
    tier: ModuleTier::Platform,
    description: 'المحلات والفروع والمستخدمين وتسجيل الدخول.',
    provider: IdentityServiceProvider::class,
    sort: 0,
);
