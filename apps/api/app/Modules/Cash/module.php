<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'cash',
    name: 'الخزنة والورديات',
    tier: ModuleTier::Core,
    description: 'الورديات والمصروفات وحركات الخزنة.',
    permissions: [
        'cash.shift' => 'فتح وقفل الوردية',
        'cash.expenses' => 'تسجيل مصروفات',
        'cash.manage' => 'حركات الخزنة',
    ],
    menu: [
        new MenuItem('/cash', 'الخزنة', 'i-lucide-wallet', 'cash.shift', group: 'sales'),
    ],
    sort: 60,
);
