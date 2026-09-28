<?php

use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'owner_app',
    name: 'تطبيق المالك',
    tier: ModuleTier::Optional,
    description: 'متابعة لحظية وموافقات من الموبايل.',
    sort: 280,
);
