<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Models\AffiliatePayout;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\PaymentRequest;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Support\AdminLog;
use App\Modules\Billing\Support\Founders;
use App\Modules\Billing\Support\Pricing;
use App\Modules\Feedback\Contracts\FeedbackInbox;
use App\Modules\Identity\Contracts\PlatformShops;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * «باقة المؤسسين» for platform admins: the offer (open / closed, prices, yearly fees, seats, end date,
 * installments, perks, the discount on extra modules, partners' share, coupons) and the founders.
 * Also the counts on the admin menu.
 */
final class AdminFounderController
{
    public function __construct(
        private readonly Founders $founders,
        private readonly Pricing $pricing,
        private readonly AdminLog $log,
    ) {}

    public function show(PlatformShops $shops): JsonResponse
    {
        $offer = $this->founders->offer();
        $list = Subscription::withoutTenancy()->whereNotNull('founder_number')->orderBy('founder_number')->get();
        $details = $shops->details($list->pluck('tenant_id')->all());
        $plans = $this->pricing->plans();
        $collected = (int) BillingInvoice::withoutTenancy()->where('cycle', Founders::CYCLE)->where('method', '!=', 'beta')->sum('total');

        return response()->json(['data' => [
            'offer' => $offer,
            'open' => $this->founders->isOpen($offer),
            'plans' => array_map(fn (string $key, array $plan) => ['key' => $key, 'name' => $plan['name'], 'monthly' => $plan['monthly']], array_keys($plans), $plans),
            'allowed_installments' => Founders::INSTALLMENTS,
            'stats' => [
                'founders' => $list->count(),
                'pending' => PaymentRequest::withoutTenancy()->where('status', 'pending')->where('cycle', Founders::CYCLE)->count(),
                'seats_left' => $this->founders->seatsLeft($offer),
                'sold' => (int) $list->sum('founder_price'),
                'collected' => $collected,
                'owed' => (int) $list->sum(fn (Subscription $s) => max(0, $s->founder_price - $s->founder_paid)),
            ],
            'founders' => $list->map(fn (Subscription $s) => [
                ...$this->founders->of($s),
                'tenant_id' => $s->tenant_id,
                'shop' => $details[$s->tenant_id] ?? null,
                'paid_until' => $s->paid_until->toIso8601String(),
                'status' => $s->status()->value,
            ])->values(),
        ]]);
    }

    public function update(Request $request): JsonResponse
    {
        $plans = array_keys($this->pricing->plans());
        $data = $request->validate([
            'enabled' => ['required', 'boolean'],
            'name' => ['required', 'string', 'max:80'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'seats' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'ends_at' => ['nullable', 'date'],
            'plans' => ['required', 'array'],
            'plans.*' => ['array'],
            'plans.*.enabled' => ['required', 'boolean'],
            'plans.*.price' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'plans.*.annual_fee' => ['required', 'integer', 'min:0', 'max:1000000000'],
            'installments' => ['required', 'array', 'min:1'],
            'installments.*' => ['integer', Rule::in(Founders::INSTALLMENTS)],
            'perks' => ['nullable', 'array', 'max:12'],
            'perks.*' => ['nullable', 'string', 'max:160'],
            'module_discount_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'affiliate_rate_percent' => ['nullable', 'integer', 'min:0', 'max:100'],
            'allow_coupons' => ['required', 'boolean'],
        ], attributes: ['seats' => 'عدد الأماكن', 'ends_at' => 'آخر يوم', 'installments' => 'الأقساط']);
        validator($data, [])->after(function (Validator $v) use ($data, $plans): void {
            foreach (array_keys($data['plans']) as $key) {
                if (! in_array($key, $plans, true)) {
                    $v->errors()->add('plans', 'فيه باقة مش موجودة.');
                }
            }
            foreach ($data['plans'] as $key => $p) {
                if ($p['enabled'] && $p['price'] <= 0) {
                    $v->errors()->add("plans.{$key}.price", 'حط سعر للباقة اللي مفتوحة.');
                }
            }
        })->validate();
        // Every plan has a row (closed when left out).
        $data['plans'] = array_combine($plans, array_map(fn (string $k) => [
            'enabled' => (bool) ($data['plans'][$k]['enabled'] ?? false),
            'price' => (int) ($data['plans'][$k]['price'] ?? 0),
            'annual_fee' => (int) ($data['plans'][$k]['annual_fee'] ?? 0),
        ], $plans));

        $before = $this->founders->offer();
        $offer = DB::transaction(fn () => $this->founders->save($data));
        $this->log->record('founders_offer_updated', details: [
            'enabled' => $offer['enabled'],
            'changed' => array_keys(array_filter($offer, fn ($value, $key) => $before[$key] !== $value, ARRAY_FILTER_USE_BOTH)),
        ]);

        return $this->show(app(PlatformShops::class));
    }

    /** The numbers on the admin menu. */
    public function badges(FeedbackInbox $inbox): JsonResponse
    {
        $counts = $inbox->counts();

        return response()->json(['data' => [
            'payments' => PaymentRequest::withoutTenancy()->where('status', 'pending')->count(),
            'payouts' => AffiliatePayout::query()->where('status', 'requested')->count(),
            'feedback' => $counts['new_feedback'],
            'errors' => $counts['open_errors'],
            'server' => DB::table('server_errors')->whereNull('resolved_at')->count(),
        ]]);
    }
}
