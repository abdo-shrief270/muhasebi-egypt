<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'e_invoicing',
    name: 'الإيصال الإلكتروني ETA',
    tier: ModuleTier::Optional,
    description: 'الربط مع منظومة الإيصال والفاتورة الإلكترونية.',
    dependsOn: ['sales'],
    permissions: [
        'e_invoicing.manage' => 'إعدادات الإيصال الإلكتروني',
    ],
    menu: [
        new MenuItem('/settings/eta', 'الإيصال الإلكتروني', 'i-lucide-file-check', 'e_invoicing.manage'),
    ],
    sort: 270,
);
