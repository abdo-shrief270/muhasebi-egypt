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
    permissions: [
        'suppliers.view' => 'عرض الموردين',
        'suppliers.manage' => 'الموردين وفواتير الشراء',
    ],
    menu: [
        new MenuItem('/purchases', 'فواتير الشراء', 'i-lucide-receipt-text', 'suppliers.view', group: 'stock'),
        new MenuItem('/suppliers', 'الموردين', 'i-lucide-truck', 'suppliers.view', group: 'stock'),
    ],
    sort: 50,
);
