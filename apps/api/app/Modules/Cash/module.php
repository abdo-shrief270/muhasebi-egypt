<?php

use App\Modules\Cash\CashServiceProvider;
use App\Support\Modules\Feature;
use App\Support\Modules\MenuItem;
use App\Support\Modules\ModuleManifest;
use App\Support\Modules\ModuleTier;

return new ModuleManifest(
    key: 'cash',
    name: 'الخزنة والورديات',
    tier: ModuleTier::Core,
    description: 'الورديات والمصروفات وحركات الخزنة.',
    permissions: [
        'cash.shift' => 'فتح وقفل الوردية',
        'cash.expenses' => 'تسجيل مصروفات',
        'cash.manage' => 'حركات الخزنة',
    ],
    menu: [
        new MenuItem('/cash', 'الخزنة', 'i-lucide-wallet', 'cash.shift', group: 'sales'),
    ],
    provider: CashServiceProvider::class,
    features: [
        new Feature('cash.expenses', 'المصروفات من الدرج', 'زرار «مصروف» في الوردية (إيجار، كهربا، أكل…). لو قفلته محدش يطلّع مصروف من الدرج.'),
        new Feature('cash.deposits', 'إيداع وسحب من الدرج', 'أزرار «إيداع» و«سحب» في الوردية لفلوس داخلة أو خارجة من غير بيع (فكة، تسليم للخزنة).'),
        new Feature('cash.blind_close', 'قفل الوردية على العمياني', 'الكاشير يعد الدرج ويكتب اللي معاه من غير ما يشوف المفروض كام ولا الفرق ولا حركات الوردية. اللي معاه صلاحية «حركات الخزنة» بس هو اللي يشوفهم.', default: false),
    ],
    featuresIntro: 'الدرج والورديات.',
    sort: 60,
);
