<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'inventory',
    name: 'المخزون',
    tier: ModuleTier::Core,
    description: 'أرصدة المخزون والحركات والجرد.',
    dependsOn: ['catalog'],
    permissions: [
        'inventory.view' => 'عرض المخزون',
        'inventory.adjust' => 'الجرد وتسوية المخزون',
    ],
    menu: [
        new MenuItem('/inventory', 'المخزون', 'i-lucide-warehouse', 'inventory.view', group: 'stock'),
    ],
    sort: 30,
);
