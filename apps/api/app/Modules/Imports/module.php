<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'imports',
    name: 'الاستيراد',
    tier: ModuleTier::Optional,
    description: 'جهات الاستيراد، الشحنات، التكلفة النهائية، الدفعات وكشوف الحساب.',
    dependsOn: ['inventory', 'suppliers'],
    permissions: [
        'imports.view' => 'عرض الاستيراد',
        'imports.manage' => 'الشحنات والدفعات',
    ],
    menu: [
        new MenuItem('/imports', 'الاستيراد', 'i-lucide-ship', 'imports.view', group: 'stock'),
    ],
    shopTypes: ['importer'],
    sort: 210,
    available: false,
);
