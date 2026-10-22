<?php

use App\Modules\Inquiries\InquiriesServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'inquiries',
    name: 'الاستعلامات',
    tier: ModuleTier::Core,
    description: 'عميل سأل على صنف أو تصليح أو موبايل: اسمه ورقمه وسؤاله وإمتى تكلّمه تاني.',
    dependsOn: ['customers'],
    permissions: [
        'inquiries.view' => 'عرض الاستعلامات',
        'inquiries.manage' => 'تسجيل الاستعلامات والرد عليها',
    ],
    menu: [
        new MenuItem('/inquiries', 'الاستعلامات', 'i-lucide-message-circle-question', 'inquiries.view', group: 'sales'),
    ],
    provider: InquiriesServiceProvider::class,
    sort: 45,
);
