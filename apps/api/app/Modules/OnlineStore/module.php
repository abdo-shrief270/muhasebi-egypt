<?php

use App\Modules\OnlineStore\OnlineStoreServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'online_store',
    name: 'المتجر الأونلاين',
    tier: ModuleTier::Optional,
    description: 'صفحة متجر بأصنافك وأسعارك ومخزونك من البرنامج، بالبحث بموديل الموبايل. الزبون يطلب ويتابع طلبه، والطلب يتحول لفاتورة بضغطة.',
    dependsOn: ['catalog', 'inventory'],
    permissions: [
        'online_store.manage' => 'إدارة المتجر الأونلاين',
        'online_store.orders' => 'طلبات المتجر الأونلاين',
    ],
    menu: [
        new MenuItem('/online-store/orders', 'طلبات المتجر', 'i-lucide-shopping-bag', 'online_store.orders', group: 'sales'),
        new MenuItem('/online-store', 'المتجر الأونلاين', 'i-lucide-store', 'online_store.manage', group: 'sales'),
    ],
    provider: OnlineStoreServiceProvider::class,
    // Every shop can add it; accessories and phone shops start on its trial.
    trialFor: ['accessories', 'phones'],
    sort: 290,
);
