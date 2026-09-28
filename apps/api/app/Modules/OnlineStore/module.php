<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'online_store',
    name: 'المتجر الأونلاين',
    tier: ModuleTier::Optional,
    description: 'كتالوج أونلاين من المخزون وطلبات على WhatsApp.',
    dependsOn: ['catalog', 'inventory'],
    permissions: [
        'online_store.manage' => 'إدارة المتجر الأونلاين',
    ],
    menu: [
        new MenuItem('/online-store', 'المتجر الأونلاين', 'i-lucide-store', 'online_store.manage', group: 'sales'),
    ],
    sort: 290,
);
