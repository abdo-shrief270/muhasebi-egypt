<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\PaymentRequest;
use App\Modules\Billing\Models\Subscription;

/** How subscriptions, requests and invoices are shown (to the owner and to platform admins). */
final class BillingView
{
    public function __construct(
        private readonly Pricing $pricing,
        private readonly Lifetime $lifetime,
    ) {}

    /** @return array<string, mixed> */
    public function subscription(Subscription $s): array
    {
        $status = $s->status();
        $plans = $this->pricing->plans();

        return [
            'status' => $status->value,
            'status_label' => $status->label(),
            'paid_up' => $status->isPaidUp(),
            'on_trial' => $s->on_trial,
            'plan' => $s->plan,
            'plan_name' => $s->plan !== null ? ($plans[$s->plan]['name'] ?? $s->plan) : null,
            'cycle' => $s->cycle,
            'modules' => array_map(fn (string $k) => ['key' => $k, 'name' => $this->pricing->name($k)], $s->modules),
            'paid_until' => $s->paid_until->toIso8601String(),
            'days_left' => (int) floor(now()->diffInDays($s->paid_until, false)),
            'suspended_reason' => $s->suspended_reason,
            'beta' => $s->inBeta(),
            'beta_until' => $s->beta_until?->toIso8601String(),
            'monthly_value' => $this->monthlyValue($s),
            'lifetime' => $this->lifetime->of($s),
        ];
    }

    /** What the subscription is worth per month (0 on trial or in a free beta period). */
    public function monthlyValue(Subscription $s): int
    {
        if ($s->plan === null || $s->on_trial || $s->inBeta()) {
            return 0;
        }
        $prices = $this->pricing->modulePrices();
        if ($s->cycle === Lifetime::CYCLE) {
            // Bought for life: the year's share of the price while paying it, then of the maintenance.
            return intdiv($s->lifetime_paid < (int) $s->lifetime_price ? (int) $s->lifetime_price : (int) $s->lifetime_maintenance, 12);
        }

        return ($this->pricing->plans()[$s->plan]['monthly'] ?? 0) + array_sum(array_map(fn (string $k) => $prices[$k] ?? 0, $s->modules));
    }

    /** @return array<string, mixed> */
    public function request(PaymentRequest $r): array
    {
        $plans = $this->pricing->plans();

        return [
            'id' => $r->id,
            'tenant_id' => $r->tenant_id,
            'plan' => $r->plan,
            'plan_name' => $plans[$r->plan]['name'] ?? $r->plan,
            'cycle' => $r->cycle,
            'modules' => array_map(fn (string $k) => ['key' => $k, 'name' => $this->pricing->name($k)], $r->modules),
            'amount' => $r->amount,
            'discount' => $r->discount,
            'credit_used' => $r->credit_used,
            'lifetime' => $r->lifetime,
            'method' => $r->method,
            'reference' => $r->reference,
            'sender_name' => $r->sender_name,
            'sender_phone' => $r->sender_phone,
            'has_proof' => $r->proof_path !== null,
            'status' => $r->status,
            'requested_by_name' => $r->requested_by_name,
            'reviewed_by_name' => $r->reviewed_by_name,
            'reviewed_at' => $r->reviewed_at?->toIso8601String(),
            'rejection_reason' => $r->rejection_reason,
            'invoice_id' => $r->invoice_id,
            'created_at' => $r->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    public function invoice(BillingInvoice $i): array
    {
        $plans = $this->pricing->plans();

        return [
            'id' => $i->id,
            'reference' => $i->reference(),
            'plan' => $i->plan,
            'plan_name' => $plans[$i->plan]['name'] ?? $i->plan,
            'cycle' => $i->cycle,
            'months' => $i->months,
            'lines' => $i->lines,
            'total' => $i->total,
            'vat' => $i->vat,
            'net' => $i->total - $i->vat,
            'period_start' => $i->period_start->toIso8601String(),
            'period_end' => $i->period_end->toIso8601String(),
            'method' => $i->method,
            'method_label' => match ($i->method) {
                'instapay' => 'InstaPay',
                'beta' => 'Beta مجانية',
                'credit' => 'من رصيد الحساب',
                default => 'تفعيل من الإدارة',
            },
            'credit_used' => $i->credit_used,
            'payment_reference' => $i->payment_reference,
            'issued_by_name' => $i->issued_by_name,
            'note' => $i->note,
            'paid_at' => $i->paid_at->toIso8601String(),
        ];
    }

    /** @return list<array<string, mixed>> the plans on sale */
    public function catalog(): array
    {
        $out = [];
        foreach ($this->pricing->openPlans() as $key => $plan) {
            $out[] = [
                'key' => $key,
                'name' => $plan['name'],
                'description' => $plan['description'],
                'monthly' => $plan['monthly'],
                'yearly' => $plan['monthly'] * $this->pricing->billedMonths('yearly'),
                'lifetime' => $plan['lifetime'],
                'maintenance' => $plan['maintenance'],
                'featured' => $plan['featured'],
                'modules' => array_map(fn (string $k) => ['key' => $k, 'name' => $this->pricing->name($k)], $plan['modules']),
            ];
        }

        return $out;
    }

    /** @return list<array{key: string, name: string, monthly: int, lifetime: int, maintenance: int}> */
    public function extraModules(): array
    {
        $out = [];
        foreach ($this->pricing->modules() as $key => $m) {
            if ($m['enabled'] && $m['monthly'] > 0) {
                $out[] = ['key' => $key, 'name' => $this->pricing->name($key), 'monthly' => $m['monthly'], 'lifetime' => $m['lifetime'], 'maintenance' => $m['maintenance']];
            }
        }

        return $out;
    }
}
