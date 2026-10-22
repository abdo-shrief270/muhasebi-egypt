<?php

use App\Modules\Payroll\PayrollServiceProvider;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'payroll',
    name: 'الرواتب',
    tier: ModuleTier::Core,
    description: 'مرتب كل موظف (شهري أو أسبوعي) وعمولة الفنيين من التذاكر والسلف والخصومات، وصرفها من الدرج أو الخزنة.',
    permissions: [
        'payroll.manage' => 'الرواتب والسلف لكل الموظفين',
    ],
    menu: [
        new MenuItem('/payroll', 'الرواتب', 'i-lucide-banknote', 'payroll.manage', group: 'settings'),
    ],
    provider: PayrollServiceProvider::class,
    sort: 90,
);
