<?php

use App\Modules\Services\ServicesServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'services',
    name: 'الشحن والتحويلات',
    tier: ModuleTier::Optional,
    description: 'تحويلات المحافظ (فودافون كاش وغيرها) وشحن الرصيد، برصيد كل محفظة وعمولتها ومكسبها لوحده.',
    dependsOn: ['cash'],
    permissions: [
        'services.manage' => 'الشحن والتحويلات (إيداع وسحب وشحن رصيد)',
        'services.fees' => 'تغيير عمولة الخدمات وقت العملية',
        'services.fund' => 'تمويل وتسييل المحافظ والأرصدة',
        'services.settings' => 'إعداد المحافظ والعمولات وإلغاء أي عملية',
    ],
    menu: [
        new MenuItem('/services', 'الشحن والتحويلات', 'i-lucide-arrow-left-right', 'services.manage', group: 'services'),
    ],
    provider: ServicesServiceProvider::class,
    shopTypes: ['accessories', 'phones'],
    sort: 240,
);
