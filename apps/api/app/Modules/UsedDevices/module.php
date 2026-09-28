<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'used_devices',
    name: 'المستعمل',
    tier: ModuleTier::Optional,
    description: 'شراء وبيع الأجهزة المستعملة مع تسجيل البطاقة وIMEI.',
    dependsOn: ['inventory', 'customers'],
    menu: [
        new MenuItem('/used-devices', 'المستعمل', 'i-lucide-smartphone'),
    ],
    sort: 230,
);
