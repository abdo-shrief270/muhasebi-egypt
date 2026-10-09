<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\BillingInvoice;
use App\Modules\Billing\Models\Subscription;
use App\Modules\ModuleManager\Contracts\TenantModules;
use App\Support\Audit\Auditor;
use App\Support\Modules\ModuleRegistry;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * A shop's subscription: created on first sight as a trial, extended when a payment is approved
 * (or an admin activates it), and the plan's modules granted through ModuleManager.
 */
final class Subscriptions
{
    public function __construct(
        private readonly Pricing $pricing,
        private readonly TenantModules $modules,
        private readonly ModuleRegistry $registry,
        private readonly Auditor $audit,
        private readonly CurrentTenant $tenant,
        private readonly Cache $cache,
    ) {}

    public function for(string $tenantId): Subscription
    {
        return Subscription::withoutTenancy()->where('tenant_id', $tenantId)->first()
            ?? Subscription::withoutTenancy()->firstOrCreate(['tenant_id' => $tenantId], [
                'tenant_id' => $tenantId,
                'on_trial' => true,
                'paid_until' => now()->addDays((int) config('billing.trial_days')),
            ]);
    }

    /**
     * Cached briefly: every shop request asks.
     *
     * @return array{status: string, paid_until: string, on_trial: bool}
     */
    public function state(string $tenantId): array
    {
        return $this->cache->remember("billing:state:{$tenantId}", 60, function () use ($tenantId): array {
            $subscription = $this->for($tenantId);

            return [
                'status' => $subscription->status()->value,
                // The status changes by itself as time passes; the cache must not outlive the next change.
                'paid_until' => $subscription->paid_until->toIso8601String(),
                'on_trial' => $subscription->on_trial,
            ];
        });
    }

    public function forget(string $tenantId): void
    {
        $this->cache->forget("billing:state:{$tenantId}");
    }

    /**
     * Pays for a period: extends the subscription, writes the invoice, grants the modules.
     *
     * @param  array{plan: string, cycle: string, modules: list<string>, months: int, lines: list<array{description: string, amount: int}>, total: int, vat: int}  $quote
     * @param  int|null  $amount  what was actually received, when an admin says it differs
     * @param  int  $creditUsed  of the total, what the shop's credit paid (already taken off its wallet)
     * @param  array{id: string, months: int}|null  $redemption  the held discount this payment used
     */
    public function activate(string $tenantId, array $quote, string $method, ?string $reference, ?string $issuedBy, ?int $amount = null, ?string $note = null, ?int $months = null, int $creditUsed = 0, ?array $redemption = null): BillingInvoice
    {
        return $this->tenant->runAs($tenantId, fn () => DB::transaction(function () use ($tenantId, $quote, $method, $reference, $issuedBy, $amount, $note, $months, $creditUsed, $redemption): BillingInvoice {
            $subscription = Subscription::query()->lockForUpdate()->find($this->for($tenantId)->id);
            $before = ['on_trial' => $subscription->on_trial, 'paid_until' => $subscription->paid_until->copy()];
            $previous = $subscription->plan !== null ? $this->pricing->modulesOf($subscription->plan, $subscription->modules) : [];

            // A new period starts when the current one (paid or trial) ends: nobody loses days.
            $start = Carbon::now()->max($subscription->paid_until);
            $months ??= $quote['months'];
            $end = $start->copy()->addMonthsNoOverflow($months);

            $lines = $quote['lines'];
            $total = $amount ?? $quote['total'];
            if ($total !== $quote['total']) {
                $label = $method === 'beta' ? 'فترة Beta مجانية' : ($total < $quote['total'] ? 'خصم' : 'تعديل');
                $lines[] = ['description' => $label, 'amount' => $total - $quote['total']];
            }

            $invoice = BillingInvoice::create([
                'tenant_id' => $tenantId,
                'number' => (int) DB::selectOne("select nextval('billing_invoice_number_seq') as n")->n,
                'plan' => $quote['plan'],
                'cycle' => $quote['cycle'],
                'months' => $months,
                'lines' => $lines,
                'total' => $total,
                'vat' => $this->pricing->vatOf($total),
                'period_start' => $start,
                'period_end' => $end,
                'method' => $method,
                'payment_reference' => $reference,
                'issued_by_name' => $issuedBy,
                'note' => $note,
                'paid_at' => now(),
                'credit_used' => $creditUsed,
            ]);

            $subscription->fill([
                'plan' => $quote['plan'],
                'cycle' => $quote['cycle'],
                'modules' => $quote['modules'],
                'on_trial' => false,
                'paid_until' => $end,
                // A beta grant marks its period; a paid one after it leaves the old mark (already past by then).
                ...($method === 'beta' ? ['beta_until' => $end] : []),
                'suspended_at' => null,
                'suspended_reason' => null,
            ])->save();

            $current = $this->pricing->modulesOf($quote['plan'], $quote['modules']);
            $this->syncModules($tenantId, $current, $previous);

            if ($redemption !== null) {
                app(Checkout::class)->consume($redemption['id'], $redemption['months']);
            }
            // Lazily: Rewards needs the Wallet, which asks back here for the subscription.
            app(Rewards::class)->afterPayment($tenantId, $before, $invoice);
            // A partner's share, when the shop came through one.
            app(Affiliates::class)->afterPayment($invoice);

            $this->audit->record(
                $method === 'beta' ? 'billing.beta_granted' : 'billing.activated',
                ($method === 'beta' ? 'فترة Beta مجانية' : 'اتفعّل الاشتراك').": باقة «{$this->pricing->plans()[$quote['plan']]['name']}» لحد ".$end->format('Y-m-d')." (فاتورة {$invoice->reference()})",
                $invoice,
                ['total' => $total, 'method' => $method],
                $tenantId,
            );
            DB::afterCommit(fn () => $this->forget($tenantId));
            $this->forget($tenantId);

            return $invoice;
        }));
    }

    /**
     * A free beta period (no payment): a zero invoice marked «beta», the plan's modules granted, the
     * shop active until the end of it. Extends from the end of the current period like a payment.
     *
     * @param  list<string>  $modules  extra modules on top of the plan
     */
    public function grantBeta(string $tenantId, string $plan, int $months, array $modules, ?string $issuedBy, ?string $note = null): BillingInvoice
    {
        $quote = $this->pricing->quote($plan, 'monthly', $modules);
        $quote['total'] *= $months;
        $period = $months === 1 ? 'شهر' : ($months <= 10 ? "{$months} شهور" : "{$months} شهر");
        $quote['lines'] = array_map(fn (array $line): array => [
            'description' => str_replace('— شهر', "— {$period}", $line['description']),
            'amount' => $line['amount'] * $months,
        ], $quote['lines']);

        return $this->activate($tenantId, $quote, 'beta', null, $issuedBy, 0, $note, $months);
    }

    /**
     * Grants the modules the subscription pays for (those that are ready) and takes back the ones it
     * no longer does. Also run by `billing:sync-modules` after a module becomes available.
     *
     * @param  list<string>  $current
     * @param  list<string>  $previous
     */
    public function syncModules(string $tenantId, array $current, array $previous = []): void
    {
        foreach (array_diff($previous, $current) as $key) {
            if ($this->registry->has($key)) {
                $this->modules->revoke($tenantId, $key);
            }
        }
        foreach ($current as $key) {
            if ($this->registry->has($key) && $this->registry->get($key)->isOptional() && $this->registry->get($key)->available) {
                $this->modules->grant($tenantId, $key, 'subscription');
            }
        }
    }
}
