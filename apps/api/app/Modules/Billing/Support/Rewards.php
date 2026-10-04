<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\Notifications\Contracts\Notifications;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Points a shop earns (config billing.rewards.points): renewing before its period ends, paying
 * yearly, finishing «ابدأ من هنا», and — for the shop that invited it — a new shop's first real
 * payment. Inside the payment's transaction.
 */
final class Rewards
{
    public function __construct(
        private readonly Wallet $wallet,
        private readonly Notifications $notifications,
        private readonly CurrentTenant $tenant,
    ) {}

    public static function points(string $type): int
    {
        return (int) config("billing.rewards.points.{$type}", 0);
    }

    /** @param  array{on_trial: bool, paid_until: Carbon}  $before  the subscription before this payment */
    public function afterPayment(string $tenantId, array $before, BillingInvoice $invoice): void
    {
        if ($invoice->method === 'beta' || $invoice->total <= 0) {
            return;
        }
        if ($invoice->cycle === 'yearly' && self::points('yearly') > 0) {
            $this->wallet->post($tenantId, 'points', 'yearly', self::points('yearly'), 'billing_invoice', $invoice->id, $invoice->reference());
        }
        if (! $before['on_trial'] && $before['paid_until']->isFuture() && self::points('early_renewal') > 0) {
            $this->wallet->post($tenantId, 'points', 'early_renewal', self::points('early_renewal'), 'billing_invoice', $invoice->id, $invoice->reference());
        }

        $subscription = Subscription::withoutTenancy()->where('tenant_id', $tenantId)->lockForUpdate()->first();
        if ($subscription?->referred_by !== null && $subscription->referral_rewarded_at === null) {
            $subscription->update(['referral_rewarded_at' => now()]);
            $referrer = $subscription->referred_by;
            $points = self::points('referral');
            if ($points > 0) {
                $this->wallet->post($referrer, 'points', 'referral', $points, 'tenant', $tenantId);
                DB::afterCommit(fn () => $this->tenant->runAs($referrer, fn () => $this->notifications->notify(
                    'billing.referral',
                    "كسبت {$points} نقطة",
                    'محل سجّل بكود الدعوة بتاعك ودفع اشتراكه. حوّل النقاط لرصيد من صفحة «الاشتراك».',
                    'i-lucide-gift',
                    '/settings/billing',
                    'owner',
                )));
            }
        }
    }

    /** «ابدأ من هنا» all done: once. */
    public function onboardingDone(string $tenantId): bool
    {
        if (self::points('onboarding') <= 0 || $this->wallet->got($tenantId, 'onboarding')) {
            return false;
        }

        return DB::transaction(function () use ($tenantId): bool {
            Subscription::withoutTenancy()->where('tenant_id', $tenantId)->lockForUpdate()->first();
            if ($this->wallet->got($tenantId, 'onboarding')) {
                return false;
            }
            $this->wallet->post($tenantId, 'points', 'onboarding', self::points('onboarding'));

            return true;
        });
    }
}
