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
    menu: [
        new MenuItem('/online-store', 'المتجر الأونلاين', 'i-lucide-store'),
    ],
    sort: 290,
);
