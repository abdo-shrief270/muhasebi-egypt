<?php

use App\Modules\Sales\SalesServiceProvider;
use App\Support\Modules\Feature;
use App\Support\Modules\FeatureSetting;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'sales',
    name: 'الكاشير',
    tier: ModuleTier::Core,
    description: 'البيع والمرتجعات والفواتير المعلّقة.',
    dependsOn: ['inventory', 'customers'],
    permissions: [
        'sales.sell' => 'البيع من الكاشير',
        'sales.view' => 'عرض المبيعات',
        'sales.discount' => 'عمل خصم',
        'sales.refund' => 'عمل مرتجع',
        'sales.void' => 'إلغاء فاتورة',
    ],
    menu: [
        new MenuItem('/pos', 'الكاشير', 'i-lucide-shopping-cart', 'sales.sell', group: 'sales'),
        new MenuItem('/sales', 'المبيعات', 'i-lucide-receipt', 'sales.view', group: 'sales'),
    ],
    provider: SalesServiceProvider::class,
    features: [
        new Feature('sales.discounts', 'الخصم على الفاتورة', 'خانة «خصم على الفاتورة» في الكاشير لمين معاه صلاحية الخصم. لو قفلتها محدش يقدر يخصم من إجمالي الفاتورة.'),
        new Feature('sales.line_discounts', 'الخصم على الصنف', 'زرار الخصم جنب كل صنف في الفاتورة. لو قفلته الخصم يبقى على الفاتورة كلها بس (لو مفتوح).'),
        new Feature('sales.price_levels', 'أسعار الجملة والفني في الكاشير', 'الكاشير يختار سعر جملة أو فني بدل القطاعي لمين معاه صلاحية الخصم. لو قفلته كل البيع بسعر القطاعي.'),
        new Feature('sales.hold_carts', 'تعليق الفواتير', 'زرار «تعليق» عشان تركن فاتورة وتكمّلها بعدين. لو قفلته الفاتورة تتدفع أو تتفضى.'),
        new Feature('sales.require_customer', 'لازم اسم العميل على كل فاتورة', 'الكاشير ميقدرش يقفل فاتورة من غير ما يختار عميل أو يكتب اسمه.', default: false),
        new Feature(
            'sales.below_cost',
            'حماية من البيع بخسارة',
            'لو سعر القطعة بعد الخصم أقل من تكلفتها: تحذير قبل الدفع أو منع البيع. الفواتير اللي اتعملت أوفلاين بتتسجل زي ما هي.',
            default: false,
            setting: FeatureSetting::choice('لما السعر يقل عن التكلفة', 'warn', ['warn' => 'حذّرني وأكمّل', 'block' => 'امنع البيع']),
        ),
        new Feature('sales.block_out_of_stock', 'منع البيع لو الصنف خلصان', 'الكاشير ميقدرش يبيع أكتر من المخزون اللي في الفرع.', default: false),
        new Feature('sales.auto_print', 'اطبع الإيصال لوحده بعد الدفع', 'أول ما الفاتورة تتسجل الإيصال يطلع على الطابعة من غير ما تدوس «اطبع».', default: false),
        new Feature('sales.receipt_link', 'لينك وQR الفاتورة للعميل', 'الإيصال عليه QR ولينك يفتح الفاتورة من الموبايل، وزرار «ابعت واتساب».'),
        new Feature('sales.returns', 'مرتجع المبيعات', 'زرار «مرتجع» في الفاتورة. لو قفلته محدش يقدر يرجّع بضاعة من عميل.'),
        new Feature(
            'sales.return_window',
            'مدة محددة للمرتجع',
            'الفاتورة متترجعش بعد عدد أيام من البيع.',
            default: false,
            setting: FeatureSetting::int('المرتجع مسموح خلال', 14, 1, 365, 'يوم'),
        ),
    ],
    featuresIntro: 'إزاي الكاشير بيبيع: الخصم والأسعار والإيصال والمرتجع.',
    sort: 10,
);
