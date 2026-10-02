<?php

use App\Support\Modules\Feature;
use App\Support\Modules\FeatureSetting;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'owner_app',
    name: 'تطبيق المالك',
    tier: ModuleTier::Optional,
    description: 'المحل في إيدك وإنت برّه: النهارده لحظة بلحظة، اللي بيحصل، وإشعارات بفرق الدرج والمرتجع وملخص آخر اليوم.',
    permissions: [
        'owner_app.alerts' => 'تطبيق المالك: النهارده، اللي بيحصل، والتنبيهات',
    ],
    menu: [
        new MenuItem('/owner', 'النهارده (المالك)', 'i-lucide-gauge', 'owner_app.alerts', group: 'reports'),
    ],
    features: [
        new Feature(
            'owner_app.cash_alert',
            'تنبيه بفرق الدرج',
            'إشعار لما وردية تتقفل بعجز أو زيادة أكبر من الحد ده.',
            setting: FeatureSetting::int('لما الفرق يعدّي', 50, 1, 100000, 'ج'),
        ),
        new Feature('owner_app.refund_alert', 'تنبيه بكل مرتجع', 'إشعار بكل مرتجع مبيعات بقيمته ورقم الفاتورة.'),
        new Feature(
            'owner_app.daily_summary',
            'ملخص آخر اليوم',
            'إشعار واحد كل يوم بالمبيعات والمكسب والمصروفات والمرتجع.',
            setting: FeatureSetting::int('الساعة (من 12 الضهر لـ 23 = 11 بالليل)', 23, 12, 23),
        ),
    ],
    featuresIntro: 'اللي يوصلك على موبايلك وإنت برّه المحل.',
    sort: 280,
);
