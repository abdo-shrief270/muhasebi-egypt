<?php

use App\Modules\Identity\IdentityServiceProvider;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'identity',
    name: 'الحساب والمستخدمين',
    tier: ModuleTier::Platform,
    description: 'المحلات والفروع والمستخدمين وتسجيل الدخول.',
    permissions: [
        'users.manage' => 'إدارة الموظفين',
        'roles.manage' => 'إدارة الأدوار والصلاحيات',
        'branches.manage' => 'إدارة الفروع',
        'audit.view' => 'عرض سجل العمليات',
    ],
    provider: IdentityServiceProvider::class,
    sort: 0,
);
