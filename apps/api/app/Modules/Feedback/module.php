<?php

use App\Modules\Feedback\FeedbackServiceProvider;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'feedback',
    name: 'الملاحظات',
    tier: ModuleTier::Core,
    description: 'ملاحظات المستخدمين من جوه البرنامج وأخطاء الواجهة، لفريق محاسبي.',
    provider: FeedbackServiceProvider::class,
    sort: 98,
);
