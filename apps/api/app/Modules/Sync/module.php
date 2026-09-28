<?php

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'sync',
    name: 'المزامنة',
    tier: ModuleTier::Platform,
    description: 'مزامنة أجهزة الكاشير الأوفلاين.',
    sort: 6,
);
