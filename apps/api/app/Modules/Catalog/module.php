<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'catalog',
    name: 'الأصناف',
    tier: ModuleTier::Core,
    description: 'التصنيفات والماركات والموديلات والأصناف والتوافق.',
    permissions: [
        'products.view' => 'عرض الأصناف',
        'products.manage' => 'إضافة وتعديل الأصناف والأسعار',
        'products.view_cost' => 'رؤية سعر التكلفة',
    ],
    menu: [
        new MenuItem('/products', 'الأصناف', 'i-lucide-package', 'products.view'),
    ],
    sort: 20,
);
