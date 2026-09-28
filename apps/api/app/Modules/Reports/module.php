<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'reports',
    name: 'التقارير',
    tier: ModuleTier::Core,
    description: 'المبيعات والأرباح والمخزون.',
    menu: [
        new MenuItem('/reports', 'التقارير', 'i-lucide-chart-column'),
    ],
    sort: 90,
);
