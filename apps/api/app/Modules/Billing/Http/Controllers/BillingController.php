<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers;

use App\Modules\Billing\Models\BillingCoupon;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\CouponRedemption;
use App\Modules\Billing\Models\PaymentRequest;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Billing\Models\WalletTransaction;
use App\Modules\Billing\Support\BillingView;
use App\Modules\Billing\Support\Checkout;
use App\Modules\Billing\Support\Pricing;
use App\Modules\Billing\Support\Rewards;
use App\Modules\Billing\Support\Subscriptions;
use App\Modules\Billing\Support\Wallet;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Onboarding\Contracts\SetupProgress;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Time\ShopDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

/**
 * The shop's side: its subscription, the plans, paying by InstaPay (a request an admin approves)
 * or from its credit, its invoices, and its rewards (credit, points, invite code, coupons). Owner
 * only, except the status every screen shows as a banner.
 */
final class BillingController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly Subscriptions $subscriptions,
        private readonly BillingView $view,
        private readonly Pricing $pricing,
        private readonly Checkout $checkout,
        private readonly Wallet $wallet,
    ) {}

    /** For the banner on every screen (any signed-in user; listed in RouteAuthorizationTest). */
    public function status(): JsonResponse
    {
        return response()->json(['data' => $this->view->subscription($this->subscriptions->for($this->tenant->idOrFail()))]);
    }

    public function show(SetupProgress $setup, Rewards $rewards, ShopDirectory $shops): JsonResponse
    {
        $tenantId = $this->tenant->idOrFail();
        // «ابدأ من هنا» all done: its points, once (checked when the owner looks at the page).
        $progress = $setup->progress([$tenantId])[$tenantId] ?? null;
        if ($progress !== null && $progress['total'] > 0 && $progress['done'] >= $progress['total']) {
            $rewards->onboardingDone($tenantId);
        }
        $subscription = $this->subscriptions->for($tenantId);
        $code = $shops->find($tenantId)?->code;
        $invited = Subscription::withoutTenancy()->where('referred_by', $tenantId);

        return response()->json(['data' => [
            'wallet' => [
                'credit' => $subscription->credit_balance,
                'points' => $subscription->points_balance,
                'points_per_pound' => (int) config('billing.rewards.points_per_pound'),
                'min_convert' => (int) config('billing.rewards.min_convert'),
                'earn' => config('billing.rewards.points'),
                'history' => WalletTransaction::query()->orderByDesc('seq')->limit(30)->get()->map(fn (WalletTransaction $t) => $t->toApi())->all(),
            ],
            'referral' => [
                'code' => $code,
                'link' => $code !== null ? rtrim((string) config('app.url'), '/').'/register?ref='.$code : null,
                'welcome' => config('billing.rewards.referral_discount'),
                'points' => Rewards::points('referral'),
                'joined' => (clone $invited)->count(),
                'paid' => (clone $invited)->whereNotNull('referral_rewarded_at')->count(),
            ],
            'discounts' => CouponRedemption::query()->whereNull('used_up_at')->orderBy('created_at')->get()->map(fn (CouponRedemption $r) => $r->toApi())->all(),
            'subscription' => $this->view->subscription($this->subscriptions->for($tenantId)),
            'plans' => $this->view->catalog(),
            'extra_modules' => $this->view->extraModules(),
            'yearly_months' => (int) config('billing.yearly_months'),
            'instapay' => config('billing.instapay'),
            'requests' => PaymentRequest::query()->latest()->limit(10)->get()->map(fn (PaymentRequest $r) => $this->view->request($r))->all(),
            'invoices' => BillingInvoice::query()->orderByDesc('number')->get()->map(fn (BillingInvoice $i) => $this->view->invoice($i))->all(),
        ]]);
    }

    public function quote(Request $request): JsonResponse
    {
        $data = $this->validateChoice($request);

        return response()->json(['data' => $this->checkout->quote($this->tenant->idOrFail(), $data['plan'], $data['cycle'], $data['modules'] ?? [])]);
    }

    /** "I paid by InstaPay": waits for an admin to check the transfer. */
    public function requestPayment(Request $request, Auditor $audit): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('owner'), 403);
        $data = $this->validateChoice($request) + $request->validate([
            'reference' => ['required', 'string', 'max:60'],
            'sender_name' => ['nullable', 'string', 'max:120'],
            'sender_phone' => ['nullable', 'string', 'max:20'],
            'proof' => ['nullable', 'file', 'mimes:jpg,jpeg,png,webp,pdf', 'max:5120'],
        ], attributes: ['reference' => 'رقم العملية', 'proof' => 'صورة التحويل']);

        if (PaymentRequest::query()->where('status', 'pending')->exists()) {
            throw new DomainRuleException('عندك طلب دفع لسه بيتراجع. استنى الرد أو الغيه الأول.', 'request_pending');
        }
        $tenantId = $this->tenant->idOrFail();
        $quote = $this->checkout->quote($tenantId, $data['plan'], $data['cycle'], $data['modules'] ?? []);
        if ($quote['due'] === 0) {
            throw new DomainRuleException('رصيدك بيغطي الاشتراك كله: ادفع من الرصيد.', 'pay_with_credit');
        }
        $user = $request->user();
        $proof = $request->file('proof')?->store("billing-proofs/{$tenantId}", 'local');

        $payment = DB::transaction(fn () => tap(PaymentRequest::create([
            'tenant_id' => $tenantId,
            'plan' => $quote['plan'],
            'cycle' => $quote['cycle'],
            'modules' => $quote['modules'],
            'amount' => $quote['due'],
            'discount' => $quote['discount'],
            'credit_used' => $quote['credit_used'],
            'redemption_id' => $quote['redemption_id'],
            'method' => 'instapay',
            'reference' => $data['reference'],
            'sender_name' => $data['sender_name'] ?? null,
            'sender_phone' => $data['sender_phone'] ?? null,
            'proof_path' => $proof,
            'status' => 'pending',
            'requested_by' => $user?->getAuthIdentifier(),
            'requested_by_name' => $user?->getAttribute('name'),
        ]), function (PaymentRequest $payment) use ($tenantId, $quote): void {
            // The credit is held now and given back if the request is rejected or cancelled.
            if ($quote['credit_used'] > 0) {
                $this->wallet->post($tenantId, 'credit', 'payment', -$quote['credit_used'], 'payment_request', $payment->id, 'طلب دفع '.$payment->reference);
            }
        }));
        $audit->record('billing.payment_requested', 'بعت طلب دفع InstaPay بـ '.number_format($quote['due'] / 100, 2)." ج (رقم العملية {$payment->reference})", $payment);

        return response()->json(['data' => $this->view->request($payment)], Response::HTTP_CREATED);
    }

    public function cancelRequest(string $paymentRequest): JsonResponse
    {
        $payment = DB::transaction(function () use ($paymentRequest): PaymentRequest {
            $payment = PaymentRequest::query()->lockForUpdate()->findOrFail($paymentRequest);
            if ($payment->status !== 'pending') {
                throw new DomainRuleException('الطلب ده اتراجع خلاص.', 'request_not_pending');
            }
            $payment->update(['status' => 'cancelled']);
            if ($payment->credit_used > 0) {
                $this->wallet->post($payment->tenant_id, 'credit', 'refund', $payment->credit_used, 'payment_request', $payment->id, 'إلغاء طلب الدفع');
            }

            return $payment;
        });

        return response()->json(['data' => $this->view->request($payment)]);
    }

    /** The credit covers the whole price: renewed at once, no transfer, no admin. */
    public function payWithCredit(Request $request, Auditor $audit): JsonResponse
    {
        $data = $this->validateChoice($request);
        $tenantId = $this->tenant->idOrFail();
        if (PaymentRequest::query()->where('status', 'pending')->exists()) {
            throw new DomainRuleException('عندك طلب دفع لسه بيتراجع. استنى الرد أو الغيه الأول.', 'request_pending');
        }
        $invoice = DB::transaction(function () use ($data, $tenantId, $request): BillingInvoice {
            Subscription::query()->lockForUpdate()->find($this->subscriptions->for($tenantId)->id);
            $quote = $this->checkout->quote($tenantId, $data['plan'], $data['cycle'], $data['modules'] ?? []);
            if ($quote['due'] > 0) {
                throw new DomainRuleException('رصيدك مش مغطي الاشتراك كله؛ الباقي بـ InstaPay.', 'credit_not_enough', context: ['due' => $quote['due']]);
            }
            $invoice = $this->subscriptions->activate(
                $tenantId, $quote, 'credit', null, $request->user()?->getAttribute('name'), creditUsed: $quote['credit_used'],
                redemption: $quote['redemption_id'] !== null ? ['id' => $quote['redemption_id'], 'months' => $quote['discount_months']] : null,
            );
            if ($quote['credit_used'] > 0) {
                $this->wallet->post($tenantId, 'credit', 'payment', -$quote['credit_used'], 'billing_invoice', $invoice->id, $invoice->reference());
            }

            return $invoice;
        });
        $audit->record('billing.paid_with_credit', 'جدّد الاشتراك من رصيد الحساب (فاتورة '.$invoice->reference().')', $invoice);

        return response()->json(['data' => $this->view->invoice($invoice)], Response::HTTP_CREATED);
    }

    /** A coupon: credit goes into the wallet now; a discount waits for the next payments. */
    public function redeem(Request $request, Auditor $audit): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:32']], attributes: ['code' => 'الكوبون']);
        $tenantId = $this->tenant->idOrFail();
        $code = mb_strtoupper(trim($data['code']));

        $label = DB::transaction(function () use ($code, $tenantId): string {
            $coupon = BillingCoupon::query()->where('code', $code)->lockForUpdate()->first()
                ?? throw new DomainRuleException('الكوبون ده مش صح.', 'coupon_invalid');
            $today = ShopDay::today();
            if (! $coupon->is_active || ($coupon->ends_on !== null && $coupon->ends_on->lt($today))) {
                throw new DomainRuleException('الكوبون ده انتهى.', 'coupon_expired');
            }
            if ($coupon->starts_on !== null && $coupon->starts_on->gt($today)) {
                throw new DomainRuleException('الكوبون ده لسه ما بدأش.', 'coupon_not_started');
            }
            if ($coupon->max_redemptions !== null && $coupon->redemptions >= $coupon->max_redemptions) {
                throw new DomainRuleException('الكوبون ده خلص.', 'coupon_used_up');
            }
            if ($coupon->new_shops_only && Checkout::hasPaid($tenantId)) {
                throw new DomainRuleException('الكوبون ده للمحلات الجديدة بس.', 'coupon_new_shops_only');
            }
            if (CouponRedemption::query()->where('coupon_id', $coupon->id)->exists() || WalletTransaction::query()->where('type', 'coupon')->where('ref_id', $coupon->id)->exists()) {
                throw new DomainRuleException('استخدمت الكوبون ده قبل كده.', 'coupon_used');
            }
            if ($coupon->kind !== 'credit' && CouponRedemption::query()->whereNull('used_up_at')->exists()) {
                throw new DomainRuleException('عندك خصم لسه ما خلصش؛ الكوبون ينفع بعده.', 'discount_held');
            }
            $coupon->increment('redemptions');
            if ($coupon->kind === 'credit') {
                $this->wallet->post($tenantId, 'credit', 'coupon', $coupon->value, 'billing_coupon', $coupon->id, $coupon->code);
            } else {
                CouponRedemption::create([
                    'tenant_id' => $tenantId, 'coupon_id' => $coupon->id, 'source' => 'coupon', 'code' => $coupon->code,
                    'kind' => $coupon->kind, 'value' => $coupon->value, 'months_left' => $coupon->kind === 'percent' ? $coupon->months : 1,
                    'created_at' => now(),
                ]);
            }

            return BillingCoupon::describe($coupon->kind, $coupon->value, $coupon->months);
        });
        $audit->record('billing.coupon', "استخدم الكوبون {$code}: {$label}");

        return response()->json(['data' => ['label' => $label]]);
    }

    /** Points → credit, at points_per_pound, from min_convert up. */
    public function convertPoints(Request $request): JsonResponse
    {
        $rate = (int) config('billing.rewards.points_per_pound');
        $data = $request->validate([
            'points' => ['required', 'integer', 'min:'.(int) config('billing.rewards.min_convert'), 'multiple_of:'.$rate],
        ], attributes: ['points' => 'النقاط']);
        $tenantId = $this->tenant->idOrFail();
        $points = (int) $data['points'];
        $credit = intdiv($points, $rate) * 100;
        DB::transaction(function () use ($tenantId, $points, $credit): void {
            $tx = $this->wallet->post($tenantId, 'points', 'convert', -$points);
            $this->wallet->post($tenantId, 'credit', 'convert', $credit, 'wallet_transaction', (string) $tx->id, "{$points} نقطة");
        });

        return response()->json(['data' => ['credit' => $credit]]);
    }

    public function invoice(string $invoice): JsonResponse
    {
        return response()->json(['data' => $this->view->invoice(BillingInvoice::query()->findOrFail($invoice))]);
    }

    /** @return array{plan: string, cycle: string, modules?: list<string>} */
    private function validateChoice(Request $request): array
    {
        return $request->validate([
            'plan' => ['required', Rule::in(array_keys($this->pricing->plans()))],
            'cycle' => ['required', Rule::in(Pricing::CYCLES)],
            'modules' => ['nullable', 'array'],
            'modules.*' => ['string', Rule::in(array_keys($this->pricing->modulePrices()))],
        ], attributes: ['plan' => 'الباقة', 'cycle' => 'مدة الاشتراك']);
    }
}
