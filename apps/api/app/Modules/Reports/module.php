<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'reports',
    name: 'التقارير',
    tier: ModuleTier::Core,
    description: 'المبيعات والأرباح والمخزون.',
    permissions: [
        'reports.view' => 'عرض التقارير',
        'reports.profit' => 'رؤية الأرباح',
    ],
    menu: [
        new MenuItem('/reports', 'التقارير', 'i-lucide-chart-column', 'reports.view'),
    ],
    sort: 90,
);
