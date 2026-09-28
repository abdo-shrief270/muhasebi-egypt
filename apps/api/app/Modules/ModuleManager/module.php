<?php

use App\Modules\ModuleManager\ModuleManagerServiceProvider;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'module_manager',
    name: 'إدارة الأقسام',
    tier: ModuleTier::Platform,
    description: 'تفعيل وإخفاء وتجربة الأقسام لكل محل.',
    provider: ModuleManagerServiceProvider::class,
    sort: 1,
);
