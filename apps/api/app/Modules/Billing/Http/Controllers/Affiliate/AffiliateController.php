<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Affiliate;

use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Models\AffiliateCommission;
use App\Modules\Billing\Models\AffiliatePayout;
use App\Modules\Billing\Models\AffiliateReferral;
use App\Modules\Billing\Support\Affiliates;
use App\Modules\Identity\Contracts\ShopDirectory;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/** A partner's own page: their link, the shops they brought, what they earned, payouts. */
final class AffiliateController
{
    public function __construct(
        private readonly Affiliates $affiliates,
        private readonly ShopDirectory $shops,
    ) {}

    public function me(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->dashboard($this->affiliate($request))]);
    }

    public function update(Request $request): JsonResponse
    {
        $affiliate = $this->affiliate($request);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:255', Rule::unique('affiliates', 'email')->ignore($affiliate->id)],
            'channel' => ['nullable', 'string', 'max:120'],
            'payout_method' => ['nullable', Rule::in(array_keys(Affiliate::PAYOUT_METHODS))],
            'payout_account' => ['nullable', 'required_with:payout_method', 'string', 'max:80'],
            'payout_name' => ['nullable', 'string', 'max:120'],
            'current_password' => ['required_with:password', 'string'],
            'password' => ['nullable', 'string', 'min:8', 'confirmed'],
        ], attributes: ['payout_account' => 'رقم الاستلام', 'payout_name' => 'الاسم على الحساب']);
        if (isset($data['password'])) {
            if (! Hash::check((string) $data['current_password'], $affiliate->getAuthPassword())) {
                throw ValidationException::withMessages(['current_password' => 'كلمة السر الحالية غلط.']);
            }
        } else {
            unset($data['password']);
        }
        unset($data['current_password']);
        if (array_key_exists('email', $data) && $data['email'] !== null) {
            $data['email'] = mb_strtolower($data['email']);
        }
        $affiliate->fill($data)->save();

        return response()->json(['data' => $this->dashboard($affiliate)]);
    }

    public function requestPayout(Request $request): JsonResponse
    {
        $this->affiliates->requestPayout($this->affiliate($request));

        return response()->json(['data' => $this->dashboard($this->affiliate($request))], 201);
    }

    /** The program's terms in numbers, for the website and the terms page (no login). */
    public function program(): JsonResponse
    {
        return response()->json(['data' => [
            'rate_percent' => (float) config('billing.affiliates.rate_percent'),
            'months' => (int) config('billing.affiliates.months'),
            'hold_days' => (int) config('billing.affiliates.hold_days'),
            'min_payout' => (int) config('billing.affiliates.min_payout'),
            'welcome_discount' => config('billing.affiliates.welcome_discount') ? (array) config('billing.rewards.referral_discount') : null,
        ]])->header('Cache-Control', 'public, max-age=3600');
    }

    /** The website saw a visit with ?aff=CODE (once per visitor session). */
    public function click(Request $request, string $code): JsonResponse
    {
        $key = 'affiliate-click:'.sha1(strtoupper($code).'|'.$request->ip());
        if (! RateLimiter::tooManyAttempts($key, 1)) {
            RateLimiter::hit($key, 6 * 3600);
            Affiliate::query()->where('code', strtoupper($code))->where('status', 'active')->increment('clicks');
        }

        return response()->json(['ok' => true]);
    }

    private function affiliate(Request $request): Affiliate
    {
        $user = $request->user();
        abort_unless($user instanceof Affiliate, 403);

        return $user;
    }

    /** @return array<string, mixed> */
    private function dashboard(Affiliate $affiliate): array
    {
        $referrals = AffiliateReferral::query()->where('affiliate_id', $affiliate->id)->latest()->limit(200)->get();
        $commissions = AffiliateCommission::query()->where('affiliate_id', $affiliate->id)->latest()->limit(100)->get();
        $names = $this->shops->findMany(array_values(array_unique([...$referrals->pluck('tenant_id')->all(), ...$commissions->pluck('tenant_id')->all()])));
        $earned = AffiliateCommission::query()->where('affiliate_id', $affiliate->id)->where('status', '<>', 'void')
            ->selectRaw('tenant_id, sum(amount) as total')->groupBy('tenant_id')->pluck('total', 'tenant_id');
        $siteUrl = (string) config('billing.affiliates.site_url');
        $appUrl = rtrim((string) config('app.url'), '/');

        return [
            'affiliate' => [
                'name' => $affiliate->name,
                'phone' => $affiliate->phone,
                'email' => $affiliate->email,
                'code' => $affiliate->code,
                'status' => $affiliate->status,
                'channel' => $affiliate->channel,
                'payout_method' => $affiliate->payout_method,
                'payout_account' => $affiliate->payout_account,
                'payout_name' => $affiliate->payout_name,
                'rate_percent' => $affiliate->rate() / 100,
                'created_at' => $affiliate->created_at->toIso8601String(),
            ],
            'links' => [
                'site' => "{$siteUrl}/?aff={$affiliate->code}",
                'pricing' => "{$siteUrl}/pricing?aff={$affiliate->code}",
                'register' => "{$appUrl}/register?aff={$affiliate->code}",
            ],
            'program' => [
                'rate_percent' => $affiliate->rate() / 100,
                'months' => (int) config('billing.affiliates.months'),
                'hold_days' => (int) config('billing.affiliates.hold_days'),
                'min_payout' => (int) config('billing.affiliates.min_payout'),
                'welcome_discount' => config('billing.affiliates.welcome_discount') ? (array) config('billing.rewards.referral_discount') : null,
                'payout_methods' => Affiliate::PAYOUT_METHODS,
            ],
            'stats' => [
                'clicks' => $affiliate->clicks,
                'signups' => AffiliateReferral::query()->where('affiliate_id', $affiliate->id)->count(),
                'paying' => AffiliateReferral::query()->where('affiliate_id', $affiliate->id)->whereNotNull('first_paid_at')->count(),
            ],
            'balances' => $this->affiliates->balances($affiliate),
            // Shops by name only: never their phone or anything else.
            'referrals' => $referrals->map(fn (AffiliateReferral $r) => [
                'shop' => $names[$r->tenant_id]->name ?? 'محل',
                'registered_at' => $r->created_at->toIso8601String(),
                'first_paid_at' => $r->first_paid_at?->toIso8601String(),
                'commission_until' => $r->commission_until?->toIso8601String(),
                'earned' => (int) ($earned[$r->tenant_id] ?? 0),
            ])->all(),
            'commissions' => $commissions->map(fn (AffiliateCommission $c) => [
                'id' => $c->id,
                'shop' => $names[$c->tenant_id]->name ?? 'محل',
                'invoice' => $c->invoice_reference,
                'base' => $c->base,
                'rate_percent' => $c->rate_bp / 100,
                'amount' => $c->amount,
                'state' => $c->state(),
                'available_at' => $c->available_at->toIso8601String(),
                'created_at' => $c->created_at->toIso8601String(),
            ])->all(),
            'payouts' => AffiliatePayout::query()->where('affiliate_id', $affiliate->id)->latest()->limit(50)->get()
                ->map(fn (AffiliatePayout $p) => [
                    'id' => $p->id,
                    'amount' => $p->amount,
                    'method' => $p->method,
                    'account' => $p->account,
                    'status' => $p->status,
                    'reference' => $p->reference,
                    'note' => $p->note,
                    'created_at' => $p->created_at->toIso8601String(),
                    'decided_at' => $p->decided_at?->toIso8601String(),
                ])->all(),
        ];
    }
}
