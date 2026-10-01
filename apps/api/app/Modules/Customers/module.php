<?php

use App\Modules\Customers\CustomersServiceProvider;
use App\Support\Modules\Feature;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'customers',
    name: 'العملاء والآجل',
    tier: ModuleTier::Core,
    description: 'ملفات العملاء وحسابات الآجل.',
    permissions: [
        'customers.view' => 'عرض العملاء',
        'customers.manage' => 'إضافة وتعديل العملاء',
        'customers.credit' => 'البيع الآجل وتحصيل المديونيات',
        'customers.export' => 'تنزيل كل بيانات عميل (قانون حماية البيانات)',
    ],
    menu: [
        new MenuItem('/customers', 'العملاء', 'i-lucide-users', 'customers.view', group: 'sales'),
    ],
    provider: CustomersServiceProvider::class,
    features: [
        new Feature('customers.credit_sales', 'البيع الآجل', 'البيع والتسليم «آجل» على حساب العميل في الكاشير والصيانة. لو قفلته كله كاش أو فيزا، والتحصيل من اللي عليهم فلوس فاضل شغال.'),
        new Feature('customers.debt_reminders', 'تذكير بالحساب على واتساب', 'زرار «فكّره على واتساب» في صفحة العميل اللي عليه فلوس.'),
    ],
    featuresIntro: 'حسابات العملاء والآجل.',
    sort: 40,
);
