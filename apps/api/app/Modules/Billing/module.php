<?php

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'billing',
    name: 'الاشتراك والفواتير',
    tier: ModuleTier::Platform,
    description: 'الباقات والاشتراكات وبوابات الدفع.',
    sort: 900,
);
