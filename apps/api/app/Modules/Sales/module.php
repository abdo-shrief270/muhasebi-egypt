<?php

use App\Modules\Sales\SalesServiceProvider;
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
    sort: 10,
);
