<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'customers',
    name: 'العملاء والآجل',
    tier: ModuleTier::Core,
    description: 'ملفات العملاء وحسابات الآجل.',
    permissions: [
        'customers.view' => 'عرض العملاء',
        'customers.manage' => 'إضافة وتعديل العملاء',
        'customers.credit' => 'البيع الآجل وتحصيل المديونيات',
    ],
    menu: [
        new MenuItem('/customers', 'العملاء', 'i-lucide-users', 'customers.view'),
    ],
    sort: 40,
);
