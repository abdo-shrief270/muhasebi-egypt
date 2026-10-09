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
    ) {}

    /**
     * @param  list<string>  $extras
     * @return array{plan: string, cycle: string, modules: list<string>, months: int, lines: list<array{description: string, amount: int}>, total: int, vat: int, price: int, discount: int, redemption_id: ?string, discount_months: int, credit_used: int, due: int}
     */
    public function quote(string $tenantId, string $plan, string $cycle, array $extras = []): array
    {
        $quote = $this->pricing->quote($plan, $cycle, $extras);
        $price = $quote['total'];
        [$redemption, $discount, $months] = $this->discount($tenantId, $quote);
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
