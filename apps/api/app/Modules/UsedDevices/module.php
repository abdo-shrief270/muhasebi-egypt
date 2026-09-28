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
    permissions: [
        'used_devices.manage' => 'شراء وبيع المستعمل',
    ],
    menu: [
        new MenuItem('/used-devices', 'المستعمل', 'i-lucide-smartphone', 'used_devices.manage', group: 'services'),
    ],
    sort: 230,
);
