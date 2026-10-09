<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Support;

use App\Modules\Onboarding\Contracts\SetupProgress;
use App\Support\Modules\FeatureAccess;
use App\Support\Modules\ModuleAccess;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;

/**
 * «ابدأ من هنا»: the first things a new shop should do, each one ticked by the shop's own data
 * (never by hand). Read-only SQL over the other modules' tables, like the Reports module.
 *
 * A user sees the steps they have the permission for; the platform admins see the shop-wide
 * progress (every step that applies to the shop: its module is usable).
 */
final class SetupChecklist implements SetupProgress
{
    public function __construct(
        private readonly ModuleAccess $modules,
        private readonly FeatureAccess $features,
    ) {}

    /**
     * The steps that apply to this shop, in order.
     *
     * @return list<array{key: string, title: string, description: string, to: string, icon: string, permission: string}>
     */
    public function definitions(string $tenantId): array
    {
        $excel = $this->features->enabled('catalog.excel_import', $tenantId);

        $steps = [
            ['key' => 'shop_info', 'title' => 'بيانات المحل على الإيصال', 'description' => 'عنوان وتليفون الفرع الرئيسي، والضريبي وآخر سطر، بيتطبعوا على كل إيصال.', 'to' => '/settings/shop', 'icon' => 'i-lucide-store', 'permission' => 'owner'],
            $excel
                ? ['key' => 'products', 'title' => 'ضيف أصنافك', 'description' => 'ارفع ملف إكسل فيه أصنافك وأسعارها مرة واحدة، أو ضيفهم واحد واحد.', 'to' => '/products/import', 'icon' => 'i-lucide-file-spreadsheet', 'permission' => 'products.manage']
                : ['key' => 'products', 'title' => 'ضيف أصنافك', 'description' => 'الأصناف اللي بتبيعها بأسعارها والباركود بتاعها.', 'to' => '/products/new', 'icon' => 'i-lucide-package-plus', 'permission' => 'products.manage'],
            ['key' => 'market_location', 'title' => 'حدد مكان محلك', 'description' => 'المحافظة والمكان على الخريطة، عشان زباين «سوق محاسبي» يلاقوك في «الأقرب ليك».', 'to' => '/settings/shop', 'icon' => 'i-lucide-map-pin', 'permission' => 'owner'],
            ['key' => 'stock', 'title' => 'سجّل البضاعة اللي عندك', 'description' => 'الكميات اللي على الرف دلوقتي (رصيد افتتاحي أو فاتورة شراء).', 'to' => '/inventory', 'icon' => 'i-lucide-boxes', 'permission' => 'inventory.adjust'],
            ['key' => 'staff', 'title' => 'ضيف موظف', 'description' => 'كل واحد بحسابه وصلاحياته، وتعرف مين عمل إيه.', 'to' => '/settings/users', 'icon' => 'i-lucide-user-plus', 'permission' => 'users.manage'],
            ['key' => 'shift', 'title' => 'افتح وردية', 'description' => 'الدرج بيبدأ برصيده وبيتقفل آخر اليوم بالعدّ.', 'to' => '/cash', 'icon' => 'i-lucide-wallet', 'permission' => 'cash.shift'],
            ['key' => 'first_sale', 'title' => 'أول فاتورة بيع', 'description' => 'بيع من الكاشير واطبع الإيصال أو ابعته واتساب.', 'to' => '/pos', 'icon' => 'i-lucide-shopping-cart', 'permission' => 'sales.sell'],
        ];
        if ($this->modules->enabled('repairs', $tenantId)) {
            $steps[] = ['key' => 'first_repair', 'title' => 'استلم أول جهاز صيانة', 'description' => 'تذكرة للجهاز، والعميل يتابعها من موبايله.', 'to' => '/repairs/new', 'icon' => 'i-lucide-wrench', 'permission' => 'repairs.create'];
        }
        $steps[] = ['key' => 'two_factor', 'title' => 'فعّل التحقق بخطوتين', 'description' => 'كود من موبايلك مع كلمة السر، عشان محدش يدخل على حساب المحل.', 'to' => '/settings/security', 'icon' => 'i-lucide-shield-check', 'permission' => 'owner'];

        return $steps;
    }

    /**
     * The steps this user can act on, each with whether the shop did it.
     *
     * @return list<array{key: string, title: string, description: string, to: string, icon: string, done: bool}>
     */
    public function forUser(Authorizable $user, string $tenantId): array
    {
        $done = $this->done([$tenantId])[$tenantId] ?? [];
        $out = [];
        foreach ($this->definitions($tenantId) as $step) {
            if (! $user->can($step['permission'])) {
                continue;
            }
            unset($step['permission']);
            $out[] = [...$step, 'done' => $done[$step['key']] ?? false];
        }

        return $out;
    }

    public function progress(array $tenantIds): array
    {
        $done = $this->done($tenantIds);
        $out = [];
        foreach ($tenantIds as $tenantId) {
            $steps = $this->definitions($tenantId);
            $missing = array_values(array_map(fn (array $s): string => $s['title'], array_filter($steps, fn (array $s): bool => ! ($done[$tenantId][$s['key']] ?? false))));
            $out[$tenantId] = ['done' => count($steps) - count($missing), 'total' => count($steps), 'missing' => $missing];
        }

        return $out;
    }

    /**
     * Which steps each shop has done (one query per step for all the shops).
     *
     * @param  list<string>  $tenantIds
     * @return array<string, array<string, bool>>
     */
    private function done(array $tenantIds): array
    {
        if ($tenantIds === []) {
            return [];
        }
        // Each check: the shops that have at least one such row (an index lookup per shop, not a scan).
        $having = fn (string $table, ?\Closure $where = null): array => DB::table('tenants')
            ->whereIn('tenants.id', $tenantIds)
            ->whereExists(function (Builder $q) use ($table, $where): void {
                $q->selectRaw('1')->from($table)->whereColumn("{$table}.tenant_id", 'tenants.id');
                if ($where !== null) {
                    $where($q);
                }
            })
            ->pluck('tenants.id')->all();

        $checks = [
            'shop_info' => $having('branches', fn (Builder $q) => $q->where('is_main', true)->whereNotNull('address')->where('address', '<>', '')->whereNotNull('phone')->where('phone', '<>', '')),
            'market_location' => $having('branches', fn (Builder $q) => $q->where('is_main', true)->whereNotNull('governorate')->whereNotNull('latitude')),
            'products' => $having('products'),
            'stock' => $having('stock_movements', fn (Builder $q) => $q->where('qty', '>', 0)->whereIn('type', ['opening', 'purchase', 'adjustment', 'transfer_in'])),
            'staff' => $having('users', fn (Builder $q) => $q->where('is_owner', false)),
            'shift' => $having('cash_shifts'),
            'first_sale' => $having('sales'),
            'first_repair' => $having('repair_tickets'),
            'two_factor' => $having('users', fn (Builder $q) => $q->where('is_owner', true)->whereNotNull('two_factor_confirmed_at')),
        ];

        $out = array_fill_keys($tenantIds, []);
        foreach ($checks as $key => $tenants) {
            foreach ($tenants as $tenantId) {
                $out[$tenantId][$key] = true;
            }
        }

        return $out;
    }
}
