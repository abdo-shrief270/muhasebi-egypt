<?php

use App\Modules\Inventory\InventoryServiceProvider;
use App\Support\Modules\Feature;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'inventory',
    name: 'المخزون',
    tier: ModuleTier::Core,
    description: 'أرصدة المخزون والحركات والجرد.',
    dependsOn: ['catalog'],
    permissions: [
        'inventory.view' => 'عرض المخزون',
        'inventory.adjust' => 'الجرد وتسوية المخزون',
    ],
    menu: [
        new MenuItem('/inventory', 'المخزون', 'i-lucide-warehouse', 'inventory.view', group: 'stock'),
        new MenuItem('/inventory/serials', 'بحث بالـ IMEI', 'i-lucide-scan-line', 'inventory.view', group: 'stock'),
    ],
    provider: InventoryServiceProvider::class,
    features: [
        new Feature('inventory.price_check', 'استعلام السعر السريع', 'زرار «استعلام عن سعر» فوق وF8 وCtrl+K: تمسح الصنف تعرف سعره ومخزونه من غير ما تفتح الكاشير.'),
    ],
    featuresIntro: 'المخزون والبحث السريع.',
    sort: 30,
);
