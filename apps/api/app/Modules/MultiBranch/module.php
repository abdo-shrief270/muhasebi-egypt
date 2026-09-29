<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'multi_branch',
    name: 'الفروع المتعددة',
    tier: ModuleTier::Optional,
    description: 'التحويلات بين الفروع والتقارير المجمّعة.',
    dependsOn: ['inventory'],
    permissions: [
        'transfers.manage' => 'التحويلات بين الفروع',
    ],
    menu: [
        new MenuItem('/transfers', 'التحويلات', 'i-lucide-git-compare-arrows', 'transfers.manage', group: 'stock', ready: false),
    ],
    sort: 260,
);
