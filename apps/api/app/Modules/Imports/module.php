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
    menu: [
        new MenuItem('/imports', 'الاستيراد', 'i-lucide-ship'),
    ],
    sort: 210,
);
