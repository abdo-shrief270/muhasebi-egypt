<?php

use App\Modules\Repairs\RepairsServiceProvider;
use App\Support\Modules\Feature;
use App\Support\Modules\FeatureSetting;
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
        'repairs.request_parts' => 'طلب قطع غيار من المخزن (بيشوف سعر الفني بس)',
        'repairs.hand_parts' => 'صرف قطع الغيار للفنيين',
        'repairs.part_price' => 'تغيير سعر القطعة في التذكرة (للعميل ده)',
    ],
    menu: [
        new MenuItem('/repairs', 'الصيانة', 'i-lucide-wrench', 'repairs.view', group: 'services'),
        new MenuItem('/repairs/parts', 'طلبات القطع', 'i-lucide-package-search', 'repairs.hand_parts', group: 'services', feature: 'repairs.part_requests'),
    ],
    provider: RepairsServiceProvider::class,
    // Accessories and phone shops take devices in too and send them to a partner repair shop.
    shopTypes: ['repair', 'accessories', 'phones'],
    trialFor: ['repair'],
    features: [
        new Feature('repairs.public_tracking', 'صفحة متابعة الجهاز للعميل', 'العميل يتابع حالة جهازه من لينك / QR على إيصال الاستلام وفي رسايل واتساب.'),
        new Feature('repairs.status_whatsapp', 'رسايل واتساب للعميل', 'زرار «ابعت واتساب» مع كل حالة (استلام، جاهز، اتسلّم…) وعدّاد «جاهزة والعميل ما اتبلغش».'),
        new Feature('repairs.deposits', 'عربون عند الاستلام', 'تاخد عربون من العميل وانت بتستلم الجهاز، ويتخصم من الحساب وقت التسليم.'),
        new Feature('repairs.warranty', 'الضمان على الصيانة', 'مدة ضمان وقت التسليم وزرار «رجع في الضمان». لو قفلته الأجهزة تتسلّم من غير ضمان.'),
        new Feature('repairs.commission', 'عمولة الفنيين', 'عمولة كل فني على الأجهزة اللي صلّحها (في الإعدادات والتذكرة والتقرير). لو قفلتها مفيش عمولة بتتحسب.'),
        new Feature(
            'repairs.part_requests',
            'الفني يطلب القطع من المخزن بسعر الفني',
            'الفني يدوّر على القطعة ويشوف سعر الفني بس، ويبعت «طلب قطعة»؛ أمين المخزن أو الكاشير يصرفها فتتسجل على التذكرة بسعر الفني، والفني يحط مكسبه في المصنعية.',
            default: false,
        ),
        new Feature(
            'repairs.price_floor',
            'أقل سعر للقطعة في التذكرة',
            'اللي بيغيّر سعر قطعة في التذكرة مينزلش تحت الحد ده؛ أقل منه محتاج مدير (صلاحية إعدادات الصيانة). لو قفلته مفيش حد.',
            setting: FeatureSetting::choice('الحد', 'cost', ['cost' => 'التكلفة', 'technician' => 'سعر الفني']),
        ),
        new Feature('repairs.outsourcing', 'تبعت الجهاز لمحل شريك', 'زرار «ابعته لمحل شريك» في التذكرة (محتاج قسم طلبات المحلات).'),
    ],
    featuresIntro: 'الاستلام والتسليم ورسايل العميل.',
    sort: 200,
);
