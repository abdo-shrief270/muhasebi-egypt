<?php

use App\Modules\Services\ServicesServiceProvider;
use App\Support\Modules\Feature;
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
    features: [
        new Feature('services.airtime', 'شحن الرصيد', 'حسابات «رصيد شحن» وعملية «شحن رصيد» في الشباك. لو قفلته الشباك للمحافظ بس (إيداع وسحب).'),
        new Feature('services.require_customer_phone', 'رقم العميل إجباري', 'مفيش عملية إيداع أو سحب أو شحن تتسجل من غير رقم موبايل العميل.', default: false),
    ],
    featuresIntro: 'شباك المحافظ والشحن.',
    sort: 240,
);
