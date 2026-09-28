<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'suppliers',
    name: 'الموردين والمشتريات',
    tier: ModuleTier::Core,
    description: 'فواتير الشراء وحسابات الموردين.',
    dependsOn: ['inventory'],
    menu: [
        new MenuItem('/suppliers', 'الموردين', 'i-lucide-truck'),
    ],
    sort: 50,
);
