<?php

use App\Support\Modules\Feature;
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
        new MenuItem('/reports', 'التقارير', 'i-lucide-chart-column', 'reports.view', group: 'reports'),
    ],
    features: [
        new Feature('reports.excel_export', 'تنزيل التقارير إكسل', 'زرار «إكسل» في كل تقرير. لو قفلته التقارير تفضل تتشاف وتتطبع بس.'),
        new Feature('reports.home_profit', 'المكسب في الصفحة الرئيسية', 'كارت «مكسب النهارده» ورسم المكسب ومكسب الشحن في الرئيسية لمين معاه صلاحية الأرباح. لو قفلته المكسب يفضل في التقارير بس.'),
    ],
    featuresIntro: 'التقارير والأرقام في الصفحة الرئيسية.',
    sort: 90,
);
