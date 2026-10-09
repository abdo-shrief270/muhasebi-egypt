<?php

use App\Modules\Marketplace\MarketplaceServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'marketplace',
    name: 'سوق محاسبي',
    tier: ModuleTier::Core,
    description: 'محلك وأصنافه بيظهروا لوحدهم في سوق محاسبي للزباين (souq.muhasebi.com)، وتقدر تخفي اللي انت عايزه.',
    permissions: [
        'marketplace.manage' => 'إعدادات الظهور في سوق محاسبي',
    ],
    menu: [
        new MenuItem('/marketplace', 'سوق محاسبي', 'i-lucide-shopping-basket', 'marketplace.manage', group: 'sales'),
    ],
    provider: MarketplaceServiceProvider::class,
    sort: 295,
);
