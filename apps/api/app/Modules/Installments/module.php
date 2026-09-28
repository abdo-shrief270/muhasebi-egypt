<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'installments',
    name: 'التقسيط',
    tier: ModuleTier::Optional,
    description: 'أقساط العملاء بتواريخ الاستحقاق.',
    dependsOn: ['customers', 'sales'],
    menu: [
        new MenuItem('/installments', 'التقسيط', 'i-lucide-calendar-clock'),
    ],
    sort: 250,
);
