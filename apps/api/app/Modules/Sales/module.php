<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'sales',
    name: 'الكاشير',
    tier: ModuleTier::Core,
    description: 'البيع والمرتجعات والفواتير المعلّقة.',
    dependsOn: ['inventory', 'customers'],
    menu: [
        new MenuItem('/pos', 'الكاشير', 'i-lucide-shopping-cart'),
        new MenuItem('/sales', 'المبيعات', 'i-lucide-receipt'),
    ],
    sort: 10,
);
