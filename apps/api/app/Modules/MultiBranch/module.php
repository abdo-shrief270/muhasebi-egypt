<?php

use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'multi_branch',
    name: 'الفروع المتعددة',
    tier: ModuleTier::Optional,
    description: 'نقل البضاعة بين الفروع: فرع يطلب، التاني يبعت، والأول يستلم، والمخزون والسيريالات والتكلفة بتتنقل صح.',
    dependsOn: ['inventory'],
    permissions: [
        'transfers.manage' => 'التحويلات بين الفروع',
    ],
    menu: [
        new MenuItem('/transfers', 'التحويلات', 'i-lucide-git-compare-arrows', 'transfers.manage', group: 'stock'),
    ],
    sort: 260,
);
