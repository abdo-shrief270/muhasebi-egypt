<?php

use App\Modules\Billing\BillingServiceProvider;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'billing',
    name: 'الاشتراك والفواتير',
    tier: ModuleTier::Platform,
    description: 'الباقات والاشتراكات والدفع (InstaPay أو تفعيل من الإدارة) ولوحة الإدارة.',
    provider: BillingServiceProvider::class,
    sort: 900,
);
