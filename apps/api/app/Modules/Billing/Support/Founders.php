<?php

declare(strict_types=1);

namespace App\Modules\Billing\Support;

use App\Modules\Billing\Models\PaymentRequest;
use App\Modules\Billing\Models\Subscription;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * «باقة المؤسسين»: a one-time price for life, which also covers the first year and may be paid in
 * N installments (each covering 12/N months), then a small yearly fee. The offer (prices, seats,
 * end date, perks, installments, the discount on extra modules, the partners' share) is edited by
 * platform admins; a shop's terms are locked on its subscription when its first founder payment is
 * approved, and it gets the next founder number. Payments go through the usual checkout with the
 * cycle «founder».
 */
final class Founders
{
    public const CYCLE = 'founder';

    /** Installment counts an admin may offer (each covers 12/N months). */
    public const INSTALLMENTS = [1, 2, 3, 4, 6, 12];

    public function __construct(private readonly Pricing $pricing) {}

    /**
     * The live offer (the defaults of config/billing.php until an admin saves one, closed).
     *
     * @return array{enabled: bool, name: string, tagline: ?string, seats: ?int, ends_at: ?string, plans: array<string, array{enabled: bool, price: int, annual_fee: int}>, installments: list<int>, perks: list<string>, module_discount_percent: int, affiliate_rate_percent: ?int, allow_coupons: bool}
     */
    public function offer(): array
    {
        $row = DB::table('billing_founder_offer')->where('id', 1)->first();
        $defaults = config('billing.founders');
        $plans = $row !== null ? json_decode($row->plans, true) : $defaults['plans'];
        $out = [];
        foreach (array_keys($this->pricing->plans()) as $key) {
            $p = $plans[$key] ?? null;
            $out[$key] = [
                'enabled' => (bool) ($p['enabled'] ?? false),
                'price' => (int) ($p['price'] ?? 0),
                'annual_fee' => (int) ($p['annual_fee'] ?? 0),
            ];
        }

        return [
            'enabled' => $row !== null && (bool) $row->enabled,
            'name' => $row->name ?? $defaults['name'],
            'tagline' => $row !== null ? $row->tagline : $defaults['tagline'],
            'seats' => $row !== null ? ($row->seats !== null ? (int) $row->seats : null) : $defaults['seats'],
            'ends_at' => $row?->ends_at !== null ? Carbon::parse($row->ends_at)->toIso8601String() : null,
            'plans' => $out,
            'installments' => array_values(array_map('intval', $row !== null ? json_decode($row->installments, true) : $defaults['installments'])),
            'perks' => array_values($row !== null ? json_decode($row->perks, true) : $defaults['perks']),
            'module_discount_percent' => (int) ($row->module_discount_percent ?? $defaults['module_discount_percent']),
            'affiliate_rate_percent' => $row !== null ? ($row->affiliate_rate_percent !== null ? (int) $row->affiliate_rate_percent : null) : $defaults['affiliate_rate_percent'],
            'allow_coupons' => (bool) ($row->allow_coupons ?? $defaults['allow_coupons']),
        ];
    }

    /** @param array<string, mixed> $data validated by the admin controller */
    public function save(array $data): array
    {
        $values = [
            'enabled' => (bool) $data['enabled'],
            'name' => $data['name'],
            'tagline' => $data['tagline'] ?? null,
            'seats' => $data['seats'] ?? null,
            // A date alone = through the end of that day in Cairo.
            'ends_at' => isset($data['ends_at']) ? (strlen((string) $data['ends_at']) === 10 ? Carbon::parse($data['ends_at'], 'Africa/Cairo')->endOfDay()->utc() : Carbon::parse($data['ends_at'])) : null,
            'plans' => json_encode($data['plans']),
            'installments' => json_encode(array_values(array_unique(array_map('intval', $data['installments'])))),
            'perks' => json_encode(array_values(array_filter(array_map(fn (?string $p) => trim((string) $p), $data['perks'] ?? [])))),
            'module_discount_percent' => (int) ($data['module_discount_percent'] ?? 0),
            'affiliate_rate_percent' => $data['affiliate_rate_percent'] ?? null,
            'allow_coupons' => (bool) ($data['allow_coupons'] ?? false),
            'updated_at' => now(),
        ];
        DB::table('billing_founder_offer')->updateOrInsert(['id' => 1], $values + ['created_at' => now()]);

        return $this->offer();
    }

    /** Founders so far. */
    public function count(): int
    {
        return Subscription::withoutTenancy()->whereNotNull('founder_number')->count();
    }

    /** Seats taken: the founders, and the first founder payments still waiting for an admin. */
    public function taken(?string $exceptTenantId = null): int
    {
        $pending = PaymentRequest::withoutTenancy()->where('status', 'pending')->where('cycle', self::CYCLE)
            ->when($exceptTenantId !== null, fn ($q) => $q->where('tenant_id', '!=', $exceptTenantId))
            ->whereNotIn('tenant_id', Subscription::withoutTenancy()->whereNotNull('founder_number')->select('tenant_id'))
            ->count();

        return $this->count() + $pending;
    }

    /** @param array<string, mixed>|null $offer */
    public function seatsLeft(?array $offer = null, ?string $exceptTenantId = null): ?int
    {
        $offer ??= $this->offer();

        return $offer['seats'] === null ? null : max(0, $offer['seats'] - $this->taken($exceptTenantId));
    }

    /**
     * New founders can join: open, before its end, seats left.
     *
     * @param  array<string, mixed>|null  $offer
     */
    public function isOpen(?array $offer = null, ?string $exceptTenantId = null): bool
    {
        $offer ??= $this->offer();

        return $offer['enabled']
            && ($offer['ends_at'] === null || Carbon::parse($offer['ends_at'])->isFuture())
            && ($offer['seats'] === null || $this->seatsLeft($offer, $exceptTenantId) > 0)
            && in_array(true, array_column($offer['plans'], 'enabled'), true);
    }

    public static function isFounder(Subscription $s): bool
    {
        return $s->founder_number !== null;
    }

    /**
     * The terms a founder payment is priced with: a founder's own (locked), else today's offer.
     * `$checkOpen` = a shop asking (an admin activating by hand may sell past the end or the seats).
     *
     * @return array{plan: string, price: int, annual_fee: int, installments: int, module_discount: int}
     */
    public function termsFor(Subscription $s, string $plan, ?int $installments, bool $checkOpen = true): array
    {
        if (self::isFounder($s)) {
            if ($plan !== $s->founder_plan) {
                throw new DomainRuleException('باقة المؤسسين بتاعتك على «'.($this->pricing->plans()[$s->founder_plan]['name'] ?? $s->founder_plan).'».', 'founder_plan_locked', 422);
            }

            return [
                'plan' => $plan,
                'price' => (int) $s->founder_price,
                'annual_fee' => (int) $s->founder_annual_fee,
                'installments' => (int) $s->founder_installments,
                'module_discount' => (int) $s->founder_module_discount,
            ];
        }
        $offer = $this->offer();
        if ($checkOpen && ! $this->isOpen($offer, $s->tenant_id)) {
            throw new DomainRuleException('باقة المؤسسين مقفولة دلوقتي.', 'founders_closed', 422);
        }
        $info = $offer['plans'][$plan] ?? null;
        if ($info === null || ! $info['enabled'] || $info['price'] <= 0) {
            throw new DomainRuleException('الباقة دي مش متاحة للمؤسسين.', 'founder_plan_unavailable', 422);
        }
        $installments ??= 1;
        $allowed = $checkOpen ? $offer['installments'] : self::INSTALLMENTS;
        if (! in_array($installments, $allowed, true)) {
            throw new DomainRuleException('عدد الأقساط ده مش متاح.', 'founder_installments_invalid', 422);
        }

        return [
            'plan' => $plan,
            'price' => $info['price'],
            'annual_fee' => $info['annual_fee'],
            'installments' => $installments,
            'module_discount' => $offer['module_discount_percent'],
        ];
    }

    /**
     * The next founder payment: the next installment of the price (the last takes the rest), or —
     * once the price is paid — the yearly fee. Extra modules cost their yearly price for the
     * months covered, less the founders' discount.
     *
     * @param  array{plan: string, price: int, annual_fee: int, installments: int, module_discount: int}  $terms
     * @param  list<string>  $extras
     * @return array{plan: string, cycle: string, modules: list<string>, months: int, lines: list<array{description: string, amount: int}>, total: int, vat: int, founder: array<string, mixed>}
     */
    public function quote(Subscription $s, array $terms, array $extras = []): array
    {
        $plans = $this->pricing->plans();
        $info = $plans[$terms['plan']] ?? throw new DomainRuleException('الباقة دي مش موجودة.', 'plan_not_found', 422);
        $prices = $this->pricing->modulePrices();
        $extras = array_values(array_unique(array_diff($extras, $info['modules'])));
        foreach ($extras as $key) {
            if (! isset($prices[$key])) {
                throw new DomainRuleException('فيه قسم مش متاح للاشتراك.', 'module_not_sold', 422, ['module' => $key]);
            }
        }

        $paid = self::isFounder($s) ? (int) $s->founder_paid : 0;
        $remaining = $terms['price'] - $paid;
        $n = max(1, $terms['installments']);
        if ($remaining > 0) {
            $per = max(1, intdiv($terms['price'], $n));
            $amount = $remaining - $per < $per ? $remaining : $per;
            $number = min($n, intdiv($paid, $per) + 1);
            $months = intdiv(12, $n);
            $kind = 'installment';
            $what = $n === 1 ? 'مدى الحياة (شامل أول سنة)' : "القسط {$number} من {$n}";
        } else {
            $amount = $terms['annual_fee'];
            $number = null;
            $months = 12;
            $kind = 'annual';
            $what = 'الرسوم السنوية';
        }

        $lines = [['description' => "باقة المؤسسين «{$info['name']}» — {$what}", 'amount' => $amount]];
        $discount = $terms['module_discount'] > 0 ? " (خصم المؤسسين {$terms['module_discount']}%)" : '';
        $billed = (int) config('billing.yearly_months');
        foreach ($extras as $key) {
            $price = intdiv($prices[$key] * $billed * $months * (100 - $terms['module_discount']), 12 * 100);
            $lines[] = ['description' => "قسم «{$this->pricing->name($key)}» — {$months} شهور{$discount}", 'amount' => $price];
        }
        $total = array_sum(array_column($lines, 'amount'));

        return [
            'plan' => $terms['plan'],
            'cycle' => self::CYCLE,
            'modules' => $extras,
            'months' => $months,
            'lines' => $lines,
            'total' => $total,
            'vat' => $this->pricing->vatOf($total),
            'founder' => [
                'kind' => $kind,
                'installment' => $number,
                'amount' => $amount,
                'remaining_after' => max(0, $remaining - ($kind === 'installment' ? $amount : 0)),
                'terms' => $terms,
            ],
        ];
    }

    /**
     * A founder payment was invoiced (inside Subscriptions::activate, the subscription locked): the
     * first one locks the terms and gives the next founder number; installments add to what's paid.
     *
     * @param  array<string, mixed>  $quote
     */
    public function afterPayment(Subscription $s, array $quote): void
    {
        $founder = $quote['founder'] ?? null;
        if ($founder === null) {
            return;
        }
        if (! self::isFounder($s)) {
            // One number at a time across the platform.
            DB::select('select pg_advisory_xact_lock(?)', [crc32('billing_founder_number')]);
            $terms = $founder['terms'];
            $s->fill([
                'founder_number' => (int) Subscription::withoutTenancy()->max('founder_number') + 1,
                'founder_since' => now(),
                'founder_plan' => $terms['plan'],
                'founder_price' => $terms['price'],
                'founder_installments' => $terms['installments'],
                'founder_annual_fee' => $terms['annual_fee'],
                'founder_module_discount' => $terms['module_discount'],
                'founder_paid' => 0,
            ]);
        }
        if ($founder['kind'] === 'installment') {
            $s->founder_paid = (int) $s->founder_paid + (int) $founder['amount'];
        }
        $s->save();
    }

    /** A partner's share (basis points) on a founder payment: the offer's own rate, never above theirs. */
    public function affiliateRate(int $theirs): int
    {
        $rate = $this->offer()['affiliate_rate_percent'];

        return $rate === null ? $theirs : min($theirs, $rate * 100);
    }

    /**
     * The offer as shops and the website see it (null while closed).
     *
     * @return array<string, mixed>|null
     */
    public function publicOffer(?string $exceptTenantId = null): ?array
    {
        $offer = $this->offer();
        if (! $this->isOpen($offer, $exceptTenantId)) {
            return null;
        }
        $plans = [];
        foreach ($this->pricing->plans() as $key => $plan) {
            $p = $offer['plans'][$key];
            if (! $p['enabled'] || $p['price'] <= 0) {
                continue;
            }
            $plans[] = [
                'key' => $key,
                'name' => $plan['name'],
                'description' => $plan['description'],
                'price' => $p['price'],
                'annual_fee' => $p['annual_fee'],
                'monthly' => $plan['monthly'],
                'featured' => (bool) ($plan['featured'] ?? false),
                'modules' => array_map(fn (string $k) => ['key' => $k, 'name' => $this->pricing->name($k)], $plan['modules']),
            ];
        }

        return [
            'name' => $offer['name'],
            'tagline' => $offer['tagline'],
            'seats' => $offer['seats'],
            'seats_left' => $this->seatsLeft($offer, $exceptTenantId),
            'ends_at' => $offer['ends_at'],
            'installments' => $offer['installments'],
            'perks' => $offer['perks'],
            'module_discount_percent' => $offer['module_discount_percent'],
            'plans' => $plans,
        ];
    }

    /**
     * A founder's own terms and where they are (null for other shops).
     *
     * @return array<string, mixed>|null
     */
    public function of(Subscription $s): ?array
    {
        if (! self::isFounder($s)) {
            return null;
        }
        $remaining = max(0, (int) $s->founder_price - (int) $s->founder_paid);

        return [
            'number' => $s->founder_number,
            'since' => $s->founder_since?->toIso8601String(),
            'plan' => $s->founder_plan,
            'plan_name' => $this->pricing->plans()[$s->founder_plan]['name'] ?? $s->founder_plan,
            'price' => (int) $s->founder_price,
            'paid' => (int) $s->founder_paid,
            'remaining' => $remaining,
            'installments' => (int) $s->founder_installments,
            'annual_fee' => (int) $s->founder_annual_fee,
            'module_discount' => (int) $s->founder_module_discount,
            'next' => $remaining > 0 ? 'installment' : 'annual',
        ];
    }
}
