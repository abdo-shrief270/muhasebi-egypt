<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'supplier_returns',
    name: 'مرتجعات الموردين',
    tier: ModuleTier::Optional,
    description: 'فرز المرتجعات والتالف حسب المصدر وأذونات المرتجع.',
    dependsOn: ['inventory', 'suppliers'],
    permissions: [
        'supplier_returns.view' => 'عرض مرتجعات الموردين',
        'supplier_returns.manage' => 'فرز وإرسال المرتجعات',
    ],
    menu: [
        new MenuItem('/supplier-returns', 'مرتجعات الموردين', 'i-lucide-undo-2', 'supplier_returns.view'),
    ],
    sort: 220,
);
