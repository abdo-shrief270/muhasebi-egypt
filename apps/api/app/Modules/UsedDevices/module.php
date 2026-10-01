<?php

use App\Modules\UsedDevices\UsedDevicesServiceProvider;
use App\Support\Modules\Feature;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'used_devices',
    name: 'المستعمل',
    tier: ModuleTier::Optional,
    description: 'شراء وبيع الأجهزة المستعملة مع تسجيل البطاقة وIMEI.',
    dependsOn: ['inventory', 'customers'],
    permissions: [
        'used_devices.manage' => 'شراء وبيع المستعمل',
        'used_devices.view_seller' => 'رؤية بيانات البايع وصور البطاقة',
    ],
    menu: [
        new MenuItem('/used-devices', 'المستعمل', 'i-lucide-smartphone', 'used_devices.manage', group: 'services'),
    ],
    provider: UsedDevicesServiceProvider::class,
    shopTypes: ['repair', 'phones'],
    features: [
        new Feature('used_devices.device_photos_required', 'صور الجهاز إجباري', 'مفيش جهاز مستعمل يتشرى من غير صورة واحدة على الأقل للجهاز نفسه (غير صور البطاقة).', default: false),
    ],
    featuresIntro: 'شراء الأجهزة المستعملة.',
    sort: 230,
);
