<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'cash',
    name: 'الخزنة والورديات',
    tier: ModuleTier::Core,
    description: 'الورديات والمصروفات وحركات الخزنة.',
    menu: [
        new MenuItem('/cash', 'الخزنة', 'i-lucide-wallet'),
    ],
    sort: 60,
);
