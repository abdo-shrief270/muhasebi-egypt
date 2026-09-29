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
        'repairs.update_status' => 'الفحص والإصلاح (الحالة، القطع، المصنعية)',
        'repairs.deliver' => 'تسليم الأجهزة وتحصيل الحساب',
        'repairs.settings' => 'تعديل قوايم الأعطال',
    ],
    menu: [new MenuItem('/repairs', 'الصيانة', 'i-lucide-wrench', 'repairs.view', group: 'services')],
    provider: RepairsServiceProvider::class,
    // Accessories and phone shops take devices in too and send them to a partner repair shop.
    shopTypes: ['repair', 'accessories', 'phones'],
    trialFor: ['repair'],
    sort: 200,
);
