<?php

use App\Modules\ShopOrders\ShopOrdersServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'shop_orders',
    name: 'الطلبات بين المحلات',
    tier: ModuleTier::Optional,
    description: 'اطلب بضاعة أو شغل صيانة من محل شريك وتابع التجهيز لحد الاستلام.',
    permissions: [
        'shop_orders.view' => 'عرض الطلبات بين المحلات',
        'shop_orders.place' => 'طلب من محل شريك',
        'shop_orders.fulfil' => 'تجهيز طلبات المحلات',
        'shop_orders.partners' => 'إدارة الشركاء',
    ],
    menu: [new MenuItem('/shop-orders', 'الطلبات بين المحلات', 'i-lucide-handshake', 'shop_orders.view', group: 'services')],
    provider: ShopOrdersServiceProvider::class,
    shopTypes: ['accessories', 'repair', 'phones', 'wholesale', 'importer'],
    sort: 300,
);
