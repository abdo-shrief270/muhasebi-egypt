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
    menu: [
        new MenuItem('/supplier-returns', 'مرتجعات الموردين', 'i-lucide-undo-2'),
    ],
    sort: 220,
);
