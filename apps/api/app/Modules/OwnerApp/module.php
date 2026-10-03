<?php

use App\Modules\OwnerApp\OwnerAppServiceProvider;
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
        'owner_app.approve' => 'الموافقة على طلبات الكاشير (خصم كبير، مرتجع، سحب من الدرج)',
    ],
    menu: [
        new MenuItem('/owner', 'النهارده (المالك)', 'i-lucide-gauge', 'owner_app.alerts', group: 'reports'),
    ],
    provider: OwnerAppServiceProvider::class,
    features: [
        new Feature(
            'owner_app.approve_discount',
            'موافقة على الخصم الكبير',
            'خصم أكبر من النسبة دي من الفاتورة يحتاج موافقتك من موبايلك، أو PIN مدير في المحل.',
            default: false,
            setting: FeatureSetting::int('لو الخصم أكتر من', 15, 1, 100, '%'),
        ),
        new Feature('owner_app.approve_below_cost', 'موافقة على البيع بخسارة', 'أي صنف هيتباع بأقل من تكلفته يحتاج موافقتك أو PIN مدير (بدل تحذير الكاشير بس).', default: false),
        new Feature(
            'owner_app.approve_return',
            'موافقة على المرتجع الكبير',
            'مرتجع مبيعات أكبر من المبلغ ده يحتاج موافقتك أو PIN مدير.',
            default: false,
            setting: FeatureSetting::int('لو المرتجع أكتر من', 1000, 1, 1000000, 'ج'),
        ),
        new Feature(
            'owner_app.approve_withdrawal',
            'موافقة على السحب والمصروف الكبير',
            'سحب أو مصروف من الدرج أكبر من المبلغ ده يحتاج موافقتك أو PIN مدير.',
            default: false,
            setting: FeatureSetting::int('لو المبلغ أكتر من', 2000, 1, 1000000, 'ج'),
        ),
        new Feature(
            'owner_app.approve_credit_limit',
            'موافقة على الآجل فوق حد العميل',
            'بدل ما البيع الآجل فوق حد العميل يترفض، الكاشير يطلب موافقتك أو PIN مدير.',
            default: false,
        ),
        new Feature(
            'owner_app.approve_two_factor',
            'الموافقة بالتحقق بخطوتين بس',
            'اللي يوافق (من الموبايل أو بالـ PIN) لازم يكون مفعّل التحقق بخطوتين على حسابه.',
            default: false,
        ),
        new Feature(
            'owner_app.approve_step_up',
            'تأكيد بالبصمة للموافقات الكبيرة',
            'موافقة على مبلغ أكبر من ده تطلب البصمة أو الـ PIN تاني على جهاز اللي بيوافق.',
            default: false,
            setting: FeatureSetting::int('لو المبلغ أكتر من', 5000, 1, 1000000, 'ج'),
        ),
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
