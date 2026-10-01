<?php

use App\Modules\Installments\InstallmentsServiceProvider;
use App\Support\Modules\Feature;
use App\Support\Modules\FeatureSetting;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'installments',
    name: 'التقسيط',
    tier: ModuleTier::Optional,
    description: 'قسّط فاتورة آجل أو حساب عميل على شهور، بمواعيد وتحصيل وتذكير على واتساب.',
    dependsOn: ['customers', 'sales'],
    permissions: [
        'installments.manage' => 'عمل وإلغاء خطط التقسيط',
        'installments.collect' => 'تحصيل الأقساط',
    ],
    menu: [
        new MenuItem('/installments', 'التقسيط', 'i-lucide-calendar-clock', 'installments.collect', group: 'sales'),
    ],
    provider: InstallmentsServiceProvider::class,
    shopTypes: ['phones', 'wholesale'],
    features: [
        new Feature(
            'installments.suggested_markup',
            'فايدة مقترحة',
            'نسبة في الشهر بتتحسب لوحدها على المبلغ المقسّط وقت عمل الخطة، وتقدر تغيّرها في كل خطة.',
            default: false,
            setting: FeatureSetting::int('النسبة في الشهر', 3, 0, 20, '%'),
        ),
        new Feature('installments.guarantor_required', 'الضامن إجباري', 'مفيش خطة تقسيط تتعمل من غير اسم ورقم موبايل ضامن.', default: false),
        new Feature('installments.reminders', 'تذكير بالأقساط على واتساب', 'زرار يجهّز رسالة للعميل بالقسط وميعاده والتأخير.', default: true),
    ],
    featuresIntro: 'تقسيط الفواتير وحسابات العملاء.',
    sort: 250,
);
