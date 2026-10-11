<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\CouponRedemption;

/**
 * What the shop pays for a choice: the price, minus the discount it holds (a coupon or the
 * referral welcome; a percent covers its remaining months of this period), minus its credit.
 */
final class Checkout
{
    public function __construct(
        private readonly Pricing $pricing,
        private readonly Subscriptions $subscriptions,
        private readonly Founders $founders,
    ) {}

    /**
     * The price before discounts: a plan for a month / a year, or — cycle «founder» — the next
     * founder payment, priced with `$terms` (a request's) or the shop's / today's offer.
     *
     * @param  list<string>  $extras
     * @param  array<string, mixed>|null  $terms
     * @return array<string, mixed>
     */
    public function base(string $tenantId, string $plan, string $cycle, array $extras = [], ?int $installments = null, ?array $terms = null, bool $checkOpen = true): array
    {
        if ($cycle !== Founders::CYCLE) {
            return $this->pricing->quote($plan, $cycle, $extras);
        }
        $subscription = $this->subscriptions->for($tenantId);

        return $this->founders->quote($subscription, $terms ?? $this->founders->termsFor($subscription, $plan, $installments, $checkOpen), $extras);
    }

    /**
     * @param  list<string>  $extras
     * @return array<string, mixed> the base quote plus price, discount, redemption_id, discount_months, credit_used, due
     */
    public function quote(string $tenantId, string $plan, string $cycle, array $extras = [], ?int $installments = null): array
    {
        $quote = $this->base($tenantId, $plan, $cycle, $extras, $installments);
        $price = $quote['total'];
        // Founder prices take no coupon unless the offer says so.
        $coupons = $cycle !== Founders::CYCLE || $this->founders->offer()['allow_coupons'];
        [$redemption, $discount, $months] = $coupons ? $this->discount($tenantId, $quote) : [null, 0, 0];
        if ($discount > 0) {
            $quote['lines'][] = ['description' => $this->discountLabel($redemption), 'amount' => -$discount];
        }
        $total = $price - $discount;
        $credit = min(max(0, $this->subscriptions->for($tenantId)->credit_balance), $total);

        return [
            ...$quote,
            'total' => $total,
            'vat' => $this->pricing->vatOf($total),
            'price' => $price,
            'discount' => $discount,
            'redemption_id' => $redemption?->id,
            'discount_months' => $months,
            'credit_used' => $credit,
            'due' => $total - $credit,
        ];
    }

    /**
     * The discount a held redemption gives on this quote.
     *
     * @param  array{total: int, months: int}  $quote
     * @return array{0: ?CouponRedemption, 1: int, 2: int} the redemption, the discount, the months it uses
     */
    public function discount(string $tenantId, array $quote, ?string $redemptionId = null): array
    {
        $redemption = CouponRedemption::withoutTenancy()->where('tenant_id', $tenantId)
            ->when($redemptionId !== null, fn ($q) => $q->whereKey($redemptionId))
            ->whereNull('used_up_at')->where('months_left', '>', 0)
            ->orderBy('created_at')->first();
        if ($redemption === null) {
            return [null, 0, 0];
        }
        if ($redemption->kind === 'amount') {
            return [$redemption, min($redemption->value, $quote['total']), 1];
        }
        $months = min($redemption->months_left, $quote['months']);

        return [$redemption, intdiv($quote['total'] * $redemption->value * $months, 100 * $quote['months']), $months];
    }

    /** A payment used it: fewer months left, or used up. */
    public function consume(string $redemptionId, int $months): void
    {
        $redemption = CouponRedemption::withoutTenancy()->lockForUpdate()->find($redemptionId);
        if ($redemption === null || $redemption->used_up_at !== null) {
            return;
        }
        $left = max(0, $redemption->months_left - max(1, $months));
        $redemption->update(['months_left' => $left, 'used_up_at' => $left === 0 ? now() : null]);
    }

    private function discountLabel(?CouponRedemption $r): string
    {
        if ($r === null) {
            return 'خصم';
        }

        return match ($r->source) {
            'referral' => "خصم الدعوة ({$r->value}%)",
            'affiliate' => "خصم الترحيب ({$r->value}%)",
            default => "كوبون {$r->code}".($r->kind === 'percent' ? " ({$r->value}%)" : ''),
        };
    }

    /** The shop has paid at least once (a free beta doesn't count). */
    public static function hasPaid(string $tenantId): bool
    {
        return BillingInvoice::withoutTenancy()->where('tenant_id', $tenantId)->where('total', '>', 0)->where('method', '!=', 'beta')->exists();
    }
}
