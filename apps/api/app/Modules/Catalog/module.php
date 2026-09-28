<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'catalog',
    name: 'الأصناف',
    tier: ModuleTier::Core,
    description: 'التصنيفات والماركات والموديلات والأصناف والتوافق.',
    menu: [
        new MenuItem('/products', 'الأصناف', 'i-lucide-package'),
    ],
    sort: 20,
);
