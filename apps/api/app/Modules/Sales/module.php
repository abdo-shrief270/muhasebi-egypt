<?php

use App\Modules\Sales\SalesServiceProvider;
use App\Support\Modules\Feature;
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
        new Feature('sales.discounts', 'الخصم في الكاشير', 'خصم على الصنف أو الفاتورة وتغيير مستوى السعر (جملة / فني) لمين معاه الصلاحية. لو قفلته محدش يقدر يخصم.'),
        new Feature('sales.block_out_of_stock', 'منع البيع لو الصنف خلصان', 'الكاشير ميقدرش يبيع أكتر من المخزون اللي في الفرع.', default: false),
        new Feature('sales.receipt_link', 'لينك وQR الفاتورة للعميل', 'الإيصال عليه QR ولينك يفتح الفاتورة من الموبايل، وزرار «ابعت واتساب».'),
    ],
    sort: 10,
);
