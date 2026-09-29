<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'services',
    name: 'الشحن والتحويلات',
    tier: ModuleTier::Optional,
    description: 'كروت الشحن وتحويلات المحافظ والعمولات.',
    dependsOn: ['cash'],
    permissions: [
        'services.manage' => 'كروت الشحن والتحويلات',
    ],
    menu: [
        new MenuItem('/services', 'الشحن والتحويلات', 'i-lucide-arrow-left-right', 'services.manage', group: 'services'),
    ],
    shopTypes: ['accessories', 'phones'],
    sort: 240,
    available: false,
);
