<?php

use App\Modules\Notifications\NotificationsServiceProvider;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'notifications',
    name: 'الإشعارات',
    tier: ModuleTier::Core,
    description: 'إشعارات داخل البرنامج بنشاط المحلات الشريكة.',
    provider: NotificationsServiceProvider::class,
    sort: 96,
);
