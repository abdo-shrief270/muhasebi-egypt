<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\PaymentRequest;
use App\Modules\Billing\Models\Subscription;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «مدى الحياة»: the shop buys its plan (and any extra modules) once — the price covers the first
 * year and may be paid in N installments, each covering 12/N months — then pays a yearly
 * maintenance fee. Prices come from the catalog (`Pricing`), switches from `BillingSettings`, both
 * edited by platform admins. A shop's price and maintenance are locked on its subscription when its
 * first lifetime payment is approved (it gets the next lifetime number). A module added later costs
 * its lifetime price with the next payment and adds its maintenance. The first `launch_seats`
 * buyers may get `launch_discount_percent` off. Payments go through the usual checkout with the
 * cycle «lifetime».
 */
final class Lifetime
{
    public const CYCLE = 'lifetime';

    /** Installment counts an admin may offer (each covers 12/N months). */
    public const INSTALLMENTS = [1, 2, 3, 4, 6, 12];

    public function __construct(
        private readonly Pricing $pricing,
        private readonly BillingSettings $settings,
    ) {}

    public static function isLifetime(Subscription $s): bool
    {
        return $s->lifetime_number !== null;
    }

    /** The plans sold for life. @return array<string, array<string, mixed>> */
    public function plans(): array
    {
        return array_filter($this->pricing->openPlans(), fn (array $p) => $p['lifetime'] > 0);
    }

    /** New shops can buy it: switched on, with at least one plan priced. */
    public function isOpen(): bool
    {
        return $this->settings->get()['lifetime_enabled'] && $this->plans() !== [];
    }

    /** Lifetime shops so far. */
    public function count(): int
    {
        return Subscription::withoutTenancy()->whereNotNull('lifetime_number')->count();
    }

    /** Bought or waiting for an admin: what the launch seats count. */
    public function taken(?string $exceptTenantId = null): int
    {
        $pending = PaymentRequest::withoutTenancy()->where('status', 'pending')->where('cycle', self::CYCLE)
            ->when($exceptTenantId !== null, fn ($q) => $q->where('tenant_id', '!=', $exceptTenantId))
            ->whereNotIn('tenant_id', Subscription::withoutTenancy()->whereNotNull('lifetime_number')->select('tenant_id'))
            ->count();

        return $this->count() + $pending;
    }

    /**
     * The launch discount for a new buyer now (null = none).
     *
     * @return array{percent: int, seats: ?int, seats_left: ?int, ends_at: ?string}|null
     */
    public function launch(?string $exceptTenantId = null): ?array
    {
        $s = $this->settings->get();
        if ($s['launch_discount_percent'] <= 0 || ($s['launch_ends_at'] !== null && Carbon::parse($s['launch_ends_at'])->isPast())) {
            return null;
        }
        $left = $s['launch_seats'] === null ? null : max(0, $s['launch_seats'] - $this->taken($exceptTenantId));
        if ($left === 0) {
            return null;
        }

        return ['percent' => $s['launch_discount_percent'], 'seats' => $s['launch_seats'], 'seats_left' => $left, 'ends_at' => $s['launch_ends_at']];
    }

    /**
     * The terms a lifetime payment is priced with: a lifetime shop's own (locked), else today's
     * prices for the plan and the chosen extras (less the launch discount). `$checkOpen` = a shop
     * asking (an admin activating by hand may sell it while it's switched off).
     *
     * @param  list<string>  $extras
     * @return array{plan: string, price: int, maintenance: int, installments: int, modules: list<string>, launch_discount: int}
     */
    public function termsFor(Subscription $s, string $plan, array $extras, ?int $installments, bool $checkOpen = true): array
    {
        if (self::isLifetime($s)) {
            if ($plan !== $s->lifetime_plan) {
                throw new DomainRuleException('السيستم اللي اشتريته على باقة «'.($this->pricing->plans()[$s->lifetime_plan]['name'] ?? $s->lifetime_plan).'».', 'lifetime_plan_locked', 422);
            }

            return [
                'plan' => $plan,
                'price' => (int) $s->lifetime_price,
                'maintenance' => (int) $s->lifetime_maintenance,
                'installments' => (int) $s->lifetime_installments,
                'modules' => $s->modules,
                'launch_discount' => 0,
            ];
        }
        if ($checkOpen && ! $this->isOpen()) {
            throw new DomainRuleException('الشراء مدى الحياة مش متاح دلوقتي.', 'lifetime_closed', 422);
        }
        $info = $this->pricing->plans()[$plan] ?? null;
        if ($info === null || ($checkOpen && ! $info['enabled']) || $info['lifetime'] <= 0) {
            throw new DomainRuleException('الباقة دي مش متاحة مدى الحياة.', 'lifetime_plan_unavailable', 422);
        }
        $installments ??= 1;
        if (! in_array($installments, $checkOpen ? $this->settings->get()['lifetime_installments'] : self::INSTALLMENTS, true)) {
            throw new DomainRuleException('عدد الأقساط ده مش متاح.', 'lifetime_installments_invalid', 422);
        }
        $extras = $this->pricing->extras($plan, $extras);
        [$price, $maintenance] = [$info['lifetime'], $info['maintenance']];
        foreach ($extras as $key) {
            $m = $this->module($key);
            $price += $m['lifetime'];
            $maintenance += $m['maintenance'];
        }
        $launch = $checkOpen ? $this->launch($s->tenant_id) : null;
        $discount = $launch !== null ? intdiv($price * $launch['percent'], 100) : 0;

        return [
            'plan' => $plan,
            'price' => $price - $discount,
            'maintenance' => $maintenance,
            'installments' => $installments,
            'modules' => $extras,
            'launch_discount' => $discount,
        ];
    }

    /**
     * The next lifetime payment: the next installment of the price (the last takes the rest) or —
     * once it's paid — a year's maintenance; plus the lifetime price of any module added now.
     *
     * @param  array{plan: string, price: int, maintenance: int, installments: int, modules: list<string>, launch_discount?: int}  $terms
     * @param  list<string>  $extras  the modules wanted (a lifetime shop keeps the ones it bought)
     * @return array<string, mixed>
     */
    public function quote(Subscription $s, array $terms, array $extras = []): array
    {
        $info = $this->pricing->plans()[$terms['plan']] ?? throw new DomainRuleException('الباقة دي مش موجودة.', 'plan_not_found', 422);
        $owned = self::isLifetime($s) ? $s->modules : [];
        $bought = self::isLifetime($s) ? $owned : $terms['modules'];
        $added = self::isLifetime($s) ? array_values(array_diff($this->pricing->extras($terms['plan'], $extras), $owned)) : [];

        $paid = self::isLifetime($s) ? (int) $s->lifetime_paid : 0;
        $remaining = $terms['price'] - $paid;
        $n = max(1, $terms['installments']);
        if ($remaining > 0) {
            $per = max(1, intdiv($terms['price'], $n));
            $amount = $remaining - $per < $per ? $remaining : $per;
            $number = min($n, intdiv($paid, $per) + 1);
            $months = intdiv(12, $n);
            $kind = 'installment';
            $what = $n === 1 ? 'شراء مدى الحياة (شامل أول سنة)' : "شراء مدى الحياة — القسط {$number} من {$n}";
        } else {
            $amount = $terms['maintenance'];
            $number = null;
            $months = 12;
            $kind = 'maintenance';
            $what = 'الصيانة والدعم — سنة';
        }

        $lines = [['description' => "باقة «{$info['name']}» — {$what}", 'amount' => $amount]];
        if (($terms['launch_discount'] ?? 0) > 0 && $kind === 'installment' && $paid === 0) {
            $lines[0]['description'] .= ' (بعد خصم الإطلاق '.number_format($terms['launch_discount'] / 100).' ج)';
        }
        if (! self::isLifetime($s) && $bought !== []) {
            $lines[0]['description'] .= ' + '.implode('، ', array_map(fn (string $k) => "قسم «{$this->pricing->name($k)}»", $bought));
        }
        $addedPrice = 0;
        $addedMaintenance = 0;
        foreach ($added as $key) {
            $m = $this->module($key);
            $addedPrice += $m['lifetime'];
            $addedMaintenance += $m['maintenance'];
            $lines[] = ['description' => "قسم «{$this->pricing->name($key)}» — مدى الحياة", 'amount' => $m['lifetime']];
        }
        $total = array_sum(array_column($lines, 'amount'));

        return [
            'plan' => $terms['plan'],
            'cycle' => self::CYCLE,
            'modules' => array_values(array_unique([...$bought, ...$added])),
            'months' => $months,
            'lines' => $lines,
            'total' => $total,
            'vat' => $this->pricing->vatOf($total),
            'lifetime' => [
                'kind' => $kind,
                'installment' => $number,
                'amount' => $amount,
                'remaining_after' => max(0, $remaining - ($kind === 'installment' ? $amount : 0)),
                'terms' => $terms,
                'added' => ['modules' => $added, 'price' => $addedPrice, 'maintenance' => $addedMaintenance],
            ],
        ];
    }

    /**
     * A lifetime payment was invoiced (inside Subscriptions::activate, the subscription locked): the
     * first one locks the terms and gives the next number; installments add to what's paid; added
     * modules add to the price (paid at once) and to the maintenance.
     *
     * @param  array<string, mixed>  $quote
     */
    public function afterPayment(Subscription $s, array $quote): void
    {
        $lifetime = $quote['lifetime'] ?? null;
        if ($lifetime === null) {
            return;
        }
        if (! self::isLifetime($s)) {
            // One number at a time across the platform.
            DB::select('select pg_advisory_xact_lock(?)', [crc32('billing_lifetime_number')]);
            $terms = $lifetime['terms'];
            $s->fill([
                'lifetime_number' => (int) Subscription::withoutTenancy()->max('lifetime_number') + 1,
                'lifetime_since' => now(),
                'lifetime_plan' => $terms['plan'],
                'lifetime_price' => $terms['price'],
                'lifetime_installments' => $terms['installments'],
                'lifetime_maintenance' => $terms['maintenance'],
                'lifetime_paid' => 0,
            ]);
        }
        if ($lifetime['kind'] === 'installment') {
            $s->lifetime_paid = (int) $s->lifetime_paid + (int) $lifetime['amount'];
        }
        $added = $lifetime['added'];
        if ($added['price'] > 0 || $added['maintenance'] > 0) {
            $s->lifetime_price = (int) $s->lifetime_price + (int) $added['price'];
            $s->lifetime_paid = (int) $s->lifetime_paid + (int) $added['price'];
            $s->lifetime_maintenance = (int) $s->lifetime_maintenance + (int) $added['maintenance'];
        }
        $s->save();
    }

    /** A partner's share (basis points) on a lifetime payment: the set rate, never above theirs. */
    public function affiliateRate(int $theirs): int
    {
        $rate = $this->settings->get()['lifetime_affiliate_rate_percent'];

        return $rate === null ? $theirs : min($theirs, $rate * 100);
    }

    public function allowsCoupons(): bool
    {
        return $this->settings->get()['lifetime_allow_coupons'];
    }

    /**
     * The option as shops and the website see it (null while switched off).
     *
     * @return array<string, mixed>|null
     */
    public function publicOffer(?string $exceptTenantId = null): ?array
    {
        if (! $this->isOpen()) {
            return null;
        }
        $s = $this->settings->get();
        $plans = [];
        foreach ($this->plans() as $key => $plan) {
            $plans[] = [
                'key' => $key,
                'name' => $plan['name'],
                'description' => $plan['description'],
                'lifetime' => $plan['lifetime'],
                'maintenance' => $plan['maintenance'],
                'monthly' => $plan['monthly'],
                'featured' => $plan['featured'],
                'modules' => array_map(fn (string $k) => ['key' => $k, 'name' => $this->pricing->name($k)], $plan['modules']),
            ];
        }
        $modules = [];
        foreach ($this->pricing->modules() as $key => $m) {
            if ($m['enabled'] && $m['monthly'] > 0 && $m['lifetime'] > 0) {
                $modules[] = ['key' => $key, 'name' => $this->pricing->name($key), 'lifetime' => $m['lifetime'], 'maintenance' => $m['maintenance']];
            }
        }

        return [
            'name' => $s['lifetime_name'],
            'tagline' => $s['lifetime_tagline'],
            'installments' => $s['lifetime_installments'],
            'perks' => $s['lifetime_perks'],
            'launch' => $this->launch($exceptTenantId),
            'plans' => $plans,
            'modules' => $modules,
        ];
    }

    /**
     * A lifetime shop's terms and where it is (null for other shops).
     *
     * @return array<string, mixed>|null
     */
    public function of(Subscription $s): ?array
    {
        if (! self::isLifetime($s)) {
            return null;
        }
        $remaining = max(0, (int) $s->lifetime_price - (int) $s->lifetime_paid);

        return [
            'number' => $s->lifetime_number,
            'since' => $s->lifetime_since?->toIso8601String(),
            'plan' => $s->lifetime_plan,
            'plan_name' => $this->pricing->plans()[$s->lifetime_plan]['name'] ?? $s->lifetime_plan,
            'price' => (int) $s->lifetime_price,
            'paid' => (int) $s->lifetime_paid,
            'remaining' => $remaining,
            'installments' => (int) $s->lifetime_installments,
            'maintenance' => (int) $s->lifetime_maintenance,
            'next' => $remaining > 0 ? 'installment' : 'maintenance',
        ];
    }

    /** @return array{monthly: int, lifetime: int, maintenance: int, enabled: bool} */
    private function module(string $key): array
    {
        $m = $this->pricing->modules()[$key] ?? null;
        if ($m === null || ! $m['enabled'] || $m['lifetime'] <= 0) {
            throw new DomainRuleException('فيه قسم مش متاح مدى الحياة.', 'module_not_sold', 422, ['module' => $key]);
        }

        return $m;
    }
}
