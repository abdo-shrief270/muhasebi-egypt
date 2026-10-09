<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Models\AffiliateCommission;
use App\Modules\Billing\Models\AffiliatePayout;
use App\Modules\Billing\Models\AffiliateReferral;
use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\CouponRedemption;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * The partner program (برنامج الشركاء): which shop came through which partner, the partner's share
 * of each real payment those shops make (for the program's months from the first one), and payouts.
 */
final class Affiliates
{
    /** Letters and digits that can't be misread when someone types a code. */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /** A code for a new partner: the one they asked for if it's free, else a fresh one. */
    public function newCode(?string $wanted = null): string
    {
        $wanted = $wanted === null ? '' : strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $wanted) ?? '');
        if (strlen($wanted) >= 4 && ! Affiliate::query()->where('code', $wanted)->exists()) {
            return substr($wanted, 0, 20);
        }
        do {
            $code = '';
            for ($i = 0; $i < 6; $i++) {
                $code .= self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)];
            }
        } while (Affiliate::query()->where('code', $code)->exists());

        return $code;
    }

    /**
     * A new shop registered with a partner's link. Ignored when the code is unknown, the partner is
     * suspended, the shop is the partner's own (same phone), or the shop already has a partner.
     */
    public function attribute(string $tenantId, string $code, ?string $shopPhone): ?AffiliateReferral
    {
        $affiliate = Affiliate::query()->where('code', strtoupper(trim($code)))->first();
        if ($affiliate === null || ! $affiliate->isActive() || ($shopPhone !== null && $shopPhone === $affiliate->phone)) {
            return null;
        }

        return DB::transaction(function () use ($affiliate, $tenantId): ?AffiliateReferral {
            if (AffiliateReferral::query()->where('tenant_id', $tenantId)->exists()) {
                return null;
            }
            $referral = AffiliateReferral::query()->create(['affiliate_id' => $affiliate->id, 'tenant_id' => $tenantId]);

            // The same welcome discount as a shop invited by another shop (only one of them).
            $welcome = (array) config('billing.rewards.referral_discount');
            if (config('billing.affiliates.welcome_discount') && (int) ($welcome['percent'] ?? 0) > 0
                && ! CouponRedemption::withoutTenancy()->where('tenant_id', $tenantId)->whereIn('source', ['referral', 'affiliate'])->exists()) {
                CouponRedemption::withoutTenancy()->create([
                    'tenant_id' => $tenantId,
                    'source' => 'affiliate',
                    'code' => $affiliate->code,
                    'kind' => 'percent',
                    'value' => (int) $welcome['percent'],
                    'months_left' => max(1, (int) ($welcome['months'] ?? 1)),
                    'created_at' => now(),
                ]);
            }

            return $referral;
        });
    }

    /**
     * A shop paid (inside Subscriptions::activate's transaction): its partner earns a share of what
     * was really paid — not VAT, not credit or points, never a beta grant — while the shop is in its
     * commission window (the program's months from its first payment).
     */
    public function afterPayment(BillingInvoice $invoice): ?AffiliateCommission
    {
        if ($invoice->method === 'beta' || $invoice->total <= 0) {
            return null;
        }
        $cash = $invoice->total - $invoice->credit_used;
        if ($cash <= 0) {
            return null;
        }
        $referral = AffiliateReferral::query()->where('tenant_id', $invoice->tenant_id)->lockForUpdate()->first();
        if ($referral === null) {
            return null;
        }
        $affiliate = Affiliate::query()->find($referral->affiliate_id);
        if ($affiliate === null || ! $affiliate->isActive()) {
            return null;
        }
        $paidAt = $invoice->paid_at ?? Carbon::now();
        if ($referral->first_paid_at === null) {
            $referral->update([
                'first_paid_at' => $paidAt,
                'commission_until' => $paidAt->copy()->addMonths((int) config('billing.affiliates.months')),
            ]);
        }
        if ($referral->commission_until !== null && $paidAt->gt($referral->commission_until)) {
            return null;
        }

        // The cash part of the invoice, without its VAT share.
        $base = intdiv($cash * ($invoice->total - $invoice->vat), $invoice->total);
        $rate = $affiliate->rate();
        $amount = intdiv($base * $rate + 5000, 10000);
        if ($amount <= 0) {
            return null;
        }

        return AffiliateCommission::query()->create([
            'affiliate_id' => $affiliate->id,
            'tenant_id' => $invoice->tenant_id,
            'invoice_id' => $invoice->id,
            'invoice_reference' => $invoice->reference(),
            'base' => $base,
            'rate_bp' => $rate,
            'amount' => $amount,
            'status' => 'pending',
            'available_at' => $paidAt->copy()->addDays((int) config('billing.affiliates.hold_days')),
        ]);
    }

    /** @return array{held: int, available: int, requested: int, paid: int} piasters */
    public function balances(Affiliate $affiliate): array
    {
        $out = ['held' => 0, 'available' => 0, 'requested' => 0, 'paid' => 0];
        foreach (AffiliateCommission::query()->where('affiliate_id', $affiliate->id)->where('status', '<>', 'void')->get() as $c) {
            $state = $c->state();
            if (isset($out[$state])) {
                $out[$state] += $c->amount;
            }
        }

        return $out;
    }

    /** Every available commission into one payout request, to the partner's payout account. */
    public function requestPayout(Affiliate $affiliate): AffiliatePayout
    {
        if ($affiliate->payout_method === null || $affiliate->payout_account === null) {
            throw new DomainRuleException('اكتب طريقة استلام فلوسك الأول (InstaPay أو محفظة أو بنك).', 'payout_method_missing');
        }

        return DB::transaction(function () use ($affiliate): AffiliatePayout {
            Affiliate::query()->whereKey($affiliate->id)->lockForUpdate()->first();
            if (AffiliatePayout::query()->where('affiliate_id', $affiliate->id)->where('status', 'requested')->exists()) {
                throw new DomainRuleException('عندك طلب سحب لسه بيتراجع. هيوصلك أول ما يتحوّل.', 'payout_pending');
            }
            $available = AffiliateCommission::query()->where('affiliate_id', $affiliate->id)
                ->where('status', 'pending')->whereNull('payout_id')->where('available_at', '<=', now())
                ->lockForUpdate()->get();
            $total = (int) $available->sum('amount');
            $min = (int) config('billing.affiliates.min_payout');
            if ($total < $min) {
                throw new DomainRuleException('أقل سحب '.Str::of((string) intdiv($min, 100))->append(' ج').'، والمتاح دلوقتي '.number_format($total / 100, 2).' ج.', 'payout_below_minimum');
            }
            $payout = AffiliatePayout::query()->create([
                'affiliate_id' => $affiliate->id,
                'amount' => $total,
                'method' => $affiliate->payout_method,
                'account' => $affiliate->payout_account,
                'account_name' => $affiliate->payout_name,
                'status' => 'requested',
            ]);
            AffiliateCommission::query()->whereIn('id', $available->pluck('id'))->update(['payout_id' => $payout->id]);

            return $payout;
        });
    }

    /** An admin sent the money. */
    public function markPaid(AffiliatePayout $payout, string $reference, ?string $by): AffiliatePayout
    {
        return DB::transaction(function () use ($payout, $reference, $by): AffiliatePayout {
            $payout = AffiliatePayout::query()->lockForUpdate()->findOrFail($payout->id);
            if ($payout->status !== 'requested') {
                throw new DomainRuleException('الطلب ده اتقفل قبل كده.', 'payout_closed');
            }
            $payout->update(['status' => 'paid', 'reference' => $reference, 'decided_by_name' => $by, 'decided_at' => now()]);
            AffiliateCommission::query()->where('payout_id', $payout->id)->update(['status' => 'paid']);

            return $payout;
        });
    }

    /** An admin refused it: its commissions are available again. */
    public function reject(AffiliatePayout $payout, string $note, ?string $by): AffiliatePayout
    {
        return DB::transaction(function () use ($payout, $note, $by): AffiliatePayout {
            $payout = AffiliatePayout::query()->lockForUpdate()->findOrFail($payout->id);
            if ($payout->status !== 'requested') {
                throw new DomainRuleException('الطلب ده اتقفل قبل كده.', 'payout_closed');
            }
            $payout->update(['status' => 'rejected', 'note' => $note, 'decided_by_name' => $by, 'decided_at' => now()]);
            AffiliateCommission::query()->where('payout_id', $payout->id)->update(['payout_id' => null]);

            return $payout;
        });
    }

    /** An admin cancels a share not paid yet (e.g. the shop got its money back). */
    public function void(AffiliateCommission $commission, string $reason): AffiliateCommission
    {
        return DB::transaction(function () use ($commission, $reason): AffiliateCommission {
            $commission = AffiliateCommission::query()->lockForUpdate()->findOrFail($commission->id);
            if ($commission->status !== 'pending' || $commission->payout_id !== null) {
                throw new DomainRuleException('مينفعش تلغي عمولة اتدفعت أو في طلب سحب (ارفض الطلب الأول).', 'commission_locked');
            }
            $commission->update(['status' => 'void', 'void_reason' => $reason]);

            return $commission;
        });
    }
}
