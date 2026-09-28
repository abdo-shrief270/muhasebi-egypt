<?php

use App\Modules\Repairs\RepairsServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'repairs',
    name: 'الصيانة',
    tier: ModuleTier::Optional,
    description: 'استلام الأجهزة، الأعطال بالاختيارات، متابعة الحالة، القطع المستخدمة، والضمان.',
    dependsOn: ['inventory', 'customers'],
    permissions: [
        'repairs.view' => 'عرض تذاكر الصيانة',
        'repairs.create' => 'استلام أجهزة',
        'repairs.update_status' => 'تغيير حالة التذكرة',
        'repairs.settings' => 'تعديل قوايم الأعطال',
    ],
    menu: [new MenuItem('/repairs', 'الصيانة', 'i-lucide-wrench', 'repairs.view')],
    provider: RepairsServiceProvider::class,
    sort: 200,
);
