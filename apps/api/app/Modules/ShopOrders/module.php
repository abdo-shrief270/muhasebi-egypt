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
    permissions: ['shop_orders.view', 'shop_orders.place', 'shop_orders.fulfil', 'shop_orders.partners'],
    menu: [new MenuItem('/shop-orders', 'الطلبات بين المحلات', 'i-lucide-handshake', 'shop_orders.view')],
    provider: ShopOrdersServiceProvider::class,
    sort: 300,
);
