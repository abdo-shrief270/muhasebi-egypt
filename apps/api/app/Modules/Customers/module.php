<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'customers',
    name: 'العملاء والآجل',
    tier: ModuleTier::Core,
    description: 'ملفات العملاء وحسابات الآجل.',
    menu: [
        new MenuItem('/customers', 'العملاء', 'i-lucide-users'),
    ],
    sort: 40,
);
