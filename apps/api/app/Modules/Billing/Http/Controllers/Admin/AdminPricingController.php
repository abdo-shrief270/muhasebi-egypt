<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Models\AffiliatePayout;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\PaymentRequest;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\AdminLog;
use App\Modules\Billing\Support\BillingSettings;
use App\Modules\Billing\Support\Lifetime;
use App\Modules\Billing\Support\Pricing;
use App\Modules\Feedback\Contracts\FeedbackInbox;
use App\Modules\Identity\Contracts\PlatformShops;
use App\Modules\Marketplace\Contracts\PhoneCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Prices for platform admins (apps/admin «الأسعار»): every plan and module (monthly, lifetime,
 * yearly maintenance, on sale or not, a plan's modules), the yearly subscription switch, the
 * lifetime option (installments, perks, launch discount, partners' share, coupons) and the shops
 * that bought it. Also the counts on the admin menu. Prices already locked by a shop never change.
 */
final class AdminPricingController
{
    public function __construct(
        private readonly Pricing $pricing,
        private readonly BillingSettings $settings,
        private readonly Lifetime $lifetime,
        private readonly AdminLog $log,
    ) {}

    public function show(PlatformShops $shops): JsonResponse
    {
        $list = Subscription::withoutTenancy()->whereNotNull('lifetime_number')->orderBy('lifetime_number')->get();
        $details = $shops->details($list->pluck('tenant_id')->all());
        $modules = [];
        foreach ($this->pricing->modules() as $key => $m) {
            $modules[] = ['key' => $key, 'name' => $this->pricing->name($key), ...$m];
        }

        return response()->json(['data' => [
            'plans' => array_map(fn (string $key, array $p) => ['key' => $key, ...$p], array_keys($this->pricing->plans()), $this->pricing->plans()),
            'modules' => $modules,
            'settings' => $this->settings->get(),
            'open' => $this->lifetime->isOpen(),
            'launch' => $this->lifetime->launch(),
            'allowed_installments' => Lifetime::INSTALLMENTS,
            'stats' => [
                'shops' => $list->count(),
                'pending' => PaymentRequest::withoutTenancy()->where('status', 'pending')->where('cycle', Lifetime::CYCLE)->count(),
                'sold' => (int) $list->sum('lifetime_price'),
                'collected' => (int) BillingInvoice::withoutTenancy()->where('cycle', Lifetime::CYCLE)->where('method', '!=', 'beta')->sum('total'),
                'owed' => (int) $list->sum(fn (Subscription $s) => max(0, $s->lifetime_price - $s->lifetime_paid)),
                'maintenance_yearly' => (int) $list->sum('lifetime_maintenance'),
            ],
            'lifetime_shops' => $list->map(fn (Subscription $s) => [
                ...$this->lifetime->of($s),
                'tenant_id' => $s->tenant_id,
                'shop' => $details[$s->tenant_id] ?? null,
                'paid_until' => $s->paid_until->toIso8601String(),
                'status' => $s->status()->value,
            ])->values(),
        ]]);
    }

    /** Plans and modules: every one sent, whole. */
    public function catalog(Request $request, PlatformShops $shops): JsonResponse
    {
        $plans = array_keys($this->pricing->plans());
        $modules = array_keys($this->pricing->modules());
        $money = ['required', 'integer', 'min:0', 'max:1000000000'];
        $data = $request->validate([
            'plans' => ['required', 'array'],
            'plans.*.name' => ['required', 'string', 'max:80'],
            'plans.*.description' => ['nullable', 'string', 'max:255'],
            'plans.*.monthly' => $money,
            'plans.*.lifetime' => $money,
            'plans.*.maintenance' => $money,
            'plans.*.enabled' => ['required', 'boolean'],
            'plans.*.featured' => ['required', 'boolean'],
            'plans.*.modules' => ['present', 'array'],
            'plans.*.modules.*' => ['string', Rule::in($modules)],
            'modules' => ['required', 'array'],
            'modules.*.monthly' => $money,
            'modules.*.lifetime' => $money,
            'modules.*.maintenance' => $money,
            'modules.*.enabled' => ['required', 'boolean'],
        ]);
        validator($data, [])->after(function (Validator $v) use ($data, $plans, $modules): void {
            if (array_diff(array_keys($data['plans']), $plans) !== [] || array_diff(array_keys($data['modules']), $modules) !== []) {
                $v->errors()->add('plans', 'فيه باقة أو قسم مش موجود.');
            }
            foreach ($data['plans'] as $key => $p) {
                if ($p['enabled'] && $p['monthly'] <= 0) {
                    $v->errors()->add("plans.{$key}.monthly", 'حط سعر شهري للباقة المفتوحة.');
                }
                if ($p['lifetime'] > 0 && $p['maintenance'] <= 0) {
                    $v->errors()->add("plans.{$key}.maintenance", 'الباقة اللي بتتباع مدى الحياة محتاجة صيانة سنوية.');
                }
            }
            if (! in_array(true, array_column($data['plans'], 'enabled'), true)) {
                $v->errors()->add('plans', 'لازم باقة واحدة على الأقل مفتوحة.');
            }
        })->validate();

        $before = ['plans' => $this->pricing->plans(), 'modules' => $this->pricing->modules()];
        $this->pricing->save($data);
        $changed = [];
        foreach (['plans', 'modules'] as $kind) {
            foreach ($data[$kind] as $key => $row) {
                foreach (['monthly', 'lifetime', 'maintenance', 'enabled'] as $field) {
                    if (($before[$kind][$key][$field] ?? null) != $row[$field]) {
                        $changed[] = "{$kind}.{$key}.{$field}";
                    }
                }
            }
        }
        $this->log->record('prices_updated', details: ['changed' => $changed]);

        return $this->show($shops);
    }

    public function settings(Request $request, PlatformShops $shops): JsonResponse
    {
        $data = $request->validate([
            'yearly_enabled' => ['required', 'boolean'],
            'yearly_months' => ['required', 'integer', 'min:1', 'max:12'],
            'lifetime_enabled' => ['required', 'boolean'],
            'lifetime_name' => ['required', 'string', 'max:80'],
            'lifetime_tagline' => ['nullable', 'string', 'max:255'],
            'lifetime_installments' => ['required', 'array', 'min:1'],
            'lifetime_installments.*' => ['integer', Rule::in(Lifetime::INSTALLMENTS)],
            'lifetime_perks' => ['nullable', 'array', 'max:12'],
            'lifetime_perks.*' => ['nullable', 'string', 'max:160'],
            'lifetime_affiliate_rate_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'lifetime_allow_coupons' => ['required', 'boolean'],
            'launch_discount_percent' => ['required', 'integer', 'min:0', 'max:90'],
            'launch_seats' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'launch_ends_at' => ['nullable', 'date'],
        ], attributes: ['lifetime_installments' => 'الأقساط', 'launch_seats' => 'عدد المحلات', 'launch_ends_at' => 'آخر يوم']);

        $before = $this->settings->get();
        $after = DB::transaction(fn () => $this->settings->save($data));
        $this->log->record('billing_settings_updated', details: [
            'changed' => array_keys(array_filter($after, fn ($value, $key) => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH)),
        ]);

        return $this->show($shops);
    }

    /** The numbers on the admin menu. */
    public function badges(FeedbackInbox $inbox, PhoneCatalog $phones): JsonResponse
    {
        $counts = $inbox->counts();

        return response()->json(['data' => [
            'payments' => PaymentRequest::withoutTenancy()->where('status', 'pending')->count(),
            'payouts' => AffiliatePayout::query()->where('status', 'requested')->count(),
            'feedback' => $counts['new_feedback'],
            'errors' => $counts['open_errors'],
            'server' => DB::table('server_errors')->whereNull('resolved_at')->count(),
            'phones' => $phones->toReview(),
        ]]);
    }
}
