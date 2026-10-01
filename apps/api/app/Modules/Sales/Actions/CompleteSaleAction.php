<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\Customers\Contracts\CustomerSummary;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialCount;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\PriceLevel;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Events\SaleCompleted;
use App\Modules\Sales\Models\Sale;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\FeatureAccess;
use App\Support\Numbering\DocumentNumbers;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Checks out a POS cart: prices come from the catalog (never the client), stock leaves FIFO,
 * money goes into the cashier's open shift, any credit (آجل) onto the customer's account — and
 * the sale, its lines and payments are saved together, or not at all.
 */
final class CompleteSaleAction
{
    /** How old the offline POS's cached catalog may be (the same week an offline sale may wait). */
    private const OFFLINE_CATALOG_MAX_AGE_DAYS = 7;

    public function __construct(
        private readonly VariantCatalog $catalog,
        private readonly StockLedger $stock,
        private readonly SerialRegistry $serials,
        private readonly CashDrawer $drawer,
        private readonly CustomerAccounts $customers,
        private readonly DocumentNumbers $numbers,
        private readonly EventRecorder $events,
        private readonly Auditor $audit,
        private readonly Auth $auth,
        private readonly FeatureAccess $features,
    ) {}

    /**
     * @param  list<array{variant_id: string, qty: int, discount: int, serials?: list<string>|null, unit_price?: int|null}>  $items  discount = piasters off the line; serials for products that track them; unit_price = what an offline POS charged
     * @param  list<array{method: PaymentMethod, amount: int, reference: string|null}>  $payments
     * @param  bool  $canDiscount  sales.discount: line / invoice discounts and non-retail price levels
     * @param  bool  $canCredit  customers.credit: part or all of the total on the customer's account
     * @param  CarbonInterface|null  $soldAt  when a sale queued offline was made (its completed_at); null = now
     * @param  bool  $belowCostConfirmed  the cashier saw the "below cost" warning and went on
     *
     * The owner's switches (FeatureAccess) apply too: which discounts and price levels exist, a
     * customer on every sale, selling below cost, credit, and no selling beyond the stock.
     */
    public function handle(
        string $tenantId,
        string $branchId,
        ?string $saleId,
        array $items,
        array $payments,
        int $discount,
        PriceLevel $priceLevel,
        bool $canDiscount,
        ?string $customerName = null,
        ?string $customerPhone = null,
        ?string $notes = null,
        ?string $customerId = null,
        bool $canCredit = false,
        ?CarbonInterface $soldAt = null,
        bool $belowCostConfirmed = false,
    ): Sale {
        // The same sale sent twice (a retry after a timeout) is saved once.
        if ($saleId !== null && ($existing = Sale::query()->find($saleId)) !== null) {
            return $existing;
        }

        $hasLineDiscount = array_filter($items, fn (array $i) => $i['discount'] > 0) !== [];
        // Switched off by the owner: nobody may (the owner included), whatever their permissions.
        if ($discount > 0) {
            $this->features->ensure('sales.discounts');
        }
        if ($hasLineDiscount) {
            $this->features->ensure('sales.line_discounts');
        }
        if ($priceLevel !== PriceLevel::Retail) {
            $this->features->ensure('sales.price_levels');
        }
        if (($discount > 0 || $hasLineDiscount || $priceLevel !== PriceLevel::Retail) && ! $canDiscount) {
            throw new DomainRuleException('مش معاك صلاحية الخصم أو تغيير مستوى السعر.', 'discount_not_allowed', 403);
        }
        $blockOutOfStock = $this->features->enabled('sales.block_out_of_stock');

        $variants = $this->catalog->find(array_column($items, 'variant_id'));
        // The owner's switch: no selling beyond the branch's stock.
        $inStock = $blockOutOfStock ? $this->stock->quantities($branchId, array_column($items, 'variant_id')) : [];
        // An offline sale keeps the price the customer paid when it was a real price of the
        // variant lately (the POS's cached catalog), even if the price changed before it synced.
        $offlinePrices = $soldAt !== null && array_filter($items, fn (array $i) => ($i['unit_price'] ?? null) !== null) !== []
            ? $this->catalog->pricesSince(array_column($items, 'variant_id'), $priceLevel->value, CarbonImmutable::instance($soldAt)->subDays(self::OFFLINE_CATALOG_MAX_AGE_DAYS))
            : [];
        $keptPrices = [];
        $lines = [];
        foreach ($items as $item) {
            $variant = $variants[$item['variant_id']] ?? throw new DomainRuleException('فيه صنف في الفاتورة مش موجود.', 'variant_not_found', 404);
            if (! $variant->isActive) {
                throw new DomainRuleException("«{$variant->displayName()}» موقوف ومينفعش يتباع.", 'variant_inactive');
            }
            if ($blockOutOfStock && $item['qty'] > ($inStock[$variant->id] ?? 0)) {
                throw new DomainRuleException("«{$variant->displayName()}» مفيش منه كفاية في الفرع (الموجود ".max(0, $inStock[$variant->id] ?? 0).').', 'out_of_stock', context: ['variant_id' => $variant->id, 'available' => $inStock[$variant->id] ?? 0]);
            }
            $unitPrice = $variant->priceFor($priceLevel->value);
            $paidPrice = $item['unit_price'] ?? null;
            if ($paidPrice !== null && $paidPrice !== $unitPrice && in_array($paidPrice, $offlinePrices[$variant->id] ?? [], true)) {
                $keptPrices[] = ['variant_id' => $variant->id, 'name' => $variant->displayName(), 'paid' => $paidPrice, 'current' => $unitPrice];
                $unitPrice = $paidPrice;
            }
            $gross = $unitPrice * $item['qty'];
            if ($item['discount'] > $gross) {
                throw new DomainRuleException("خصم «{$variant->displayName()}» أكبر من سعره.", 'line_discount_too_large');
            }
            $serials = SerialCount::check($variant->displayName(), $variant->trackSerial, $item['qty'], $item['serials'] ?? null, $variant->id);
            $lines[] = ['variant' => $variant, 'qty' => $item['qty'], 'unit_price' => $unitPrice, 'discount' => $item['discount'], 'line_total' => $gross - $item['discount'], 'serials' => $serials === null ? null : $this->serials->normalize($serials)];
        }

        $subtotal = array_sum(array_column($lines, 'line_total'));
        if ($discount > $subtotal) {
            throw new DomainRuleException('الخصم أكبر من إجمالي الفاتورة.', 'discount_too_large');
        }
        $total = $subtotal - $discount;

        // A sale made offline already happened: the owner's "below cost" rule can't stop it any more.
        if ($soldAt === null && $this->features->enabled('sales.below_cost')) {
            $this->guardBelowCost($branchId, $lines, $subtotal, $total, $belowCostConfirmed);
        }

        if ($customerId === null && trim((string) $customerName) === '' && $this->features->enabled('sales.require_customer')) {
            throw new DomainRuleException('اختار العميل أو اكتب اسمه الأول.', 'customer_required');
        }

        $customer = null;
        if ($customerId !== null) {
            $customer = $this->customers->find($customerId) ?? throw new DomainRuleException('العميل مش موجود.', 'customer_not_found', 404);
            $customerName = $customer->name;
            $customerPhone = $customer->phone;
        }
        $credit = array_sum(array_map(fn (array $p) => $p['method'] === PaymentMethod::Credit ? $p['amount'] : 0, $payments));
        if ($credit > 0 && $customer === null) {
            throw new DomainRuleException('اختار العميل الأول عشان تبيع آجل.', 'credit_needs_customer');
        }
        if ($credit > 0) {
            $this->features->ensure('customers.credit_sales');
        }
        if ($credit > 0 && ! $canCredit) {
            throw new DomainRuleException('مش معاك صلاحية البيع الآجل.', 'credit_not_allowed', 403);
        }

        $paid = array_sum(array_column($payments, 'amount'));
        $cash = array_sum(array_map(fn (array $p) => $p['method'] === PaymentMethod::Cash ? $p['amount'] : 0, $payments));
        if ($paid < $total) {
            throw new DomainRuleException('المدفوع أقل من المطلوب. الباقي ممكن يتحط آجل على العميل.', 'underpaid', context: ['missing' => $total - $paid]);
        }
        // Change can only come out of cash: a card or wallet payment above the total is a typo.
        $change = $paid - $total;
        if ($change > $cash) {
            throw new DomainRuleException('الفيزا والمحافظ والآجل مينفعش يزيدوا عن المطلوب؛ الباقي بيرجع من الكاش بس.', 'overpaid_non_cash');
        }

        try {
            return $this->save($tenantId, $branchId, $saleId, $lines, $payments, $discount, $priceLevel, $customer, $customerName, $customerPhone, $notes, $subtotal, $total, $paid, $change, $credit, $soldAt, $keptPrices);
        } catch (UniqueConstraintViolationException $e) {
            // The same id arrived twice at once: the other request saved it.
            return ($saleId !== null ? Sale::query()->find($saleId) : null) ?? throw $e;
        }
    }

    /**
     * The owner's «حماية من البيع بخسارة»: a unit sold for less than the branch's average cost
     * (after its line discount and its share of the invoice discount) is refused, or — in "warn"
     * mode — needs the cashier to confirm.
     *
     * @param  list<array{variant: VariantSummary, qty: int, unit_price: int, discount: int, line_total: int, serials: list<string>|null}>  $lines
     */
    private function guardBelowCost(string $branchId, array $lines, int $subtotal, int $total, bool $confirmed): void
    {
        $costs = $this->stock->averageCosts($branchId, array_map(fn (array $l) => $l['variant']->id, $lines));
        $below = [];
        foreach ($lines as $line) {
            $cost = $costs[$line['variant']->id] ?? 0;
            $net = $subtotal > 0 ? intdiv($line['line_total'] * $total, $subtotal) : 0;
            if ($cost > 0 && $net < $cost * $line['qty']) {
                $below[] = ['variant_id' => $line['variant']->id, 'name' => $line['variant']->displayName()];
            }
        }
        if ($below === []) {
            return;
        }
        $names = implode('، ', array_column($below, 'name'));
        if ($this->features->setting('sales.below_cost') === 'block') {
            throw new DomainRuleException("«{$names}» هيتباع بأقل من تكلفته، والمحل مانع البيع بخسارة.", 'below_cost', context: ['lines' => $below]);
        }
        if (! $confirmed) {
            throw new DomainRuleException("«{$names}» هيتباع بأقل من تكلفته. متأكد؟", 'below_cost_confirm', 409, ['lines' => $below]);
        }
    }

    /**
     * @param  list<array{variant: VariantSummary, qty: int, unit_price: int, discount: int, line_total: int, serials: list<string>|null}>  $lines
     * @param  list<array{method: PaymentMethod, amount: int, reference: string|null}>  $payments
     * @param  list<array{variant_id: string, name: string, paid: int, current: int}>  $keptPrices
     */
    private function save(
        string $tenantId,
        string $branchId,
        ?string $saleId,
        array $lines,
        array $payments,
        int $discount,
        PriceLevel $priceLevel,
        ?CustomerSummary $customer,
        ?string $customerName,
        ?string $customerPhone,
        ?string $notes,
        int $subtotal,
        int $total,
        int $paid,
        int $change,
        int $credit,
        ?CarbonInterface $soldAt,
        array $keptPrices = [],
    ): Sale {
        return DB::transaction(function () use ($tenantId, $branchId, $saleId, $lines, $payments, $discount, $priceLevel, $customer, $customerName, $customerPhone, $notes, $subtotal, $total, $paid, $change, $credit, $soldAt, $keptPrices): Sale {
            $user = $this->auth->guard('sanctum')->user();

            $sale = new Sale([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'number' => $this->numbers->next($tenantId, 'sale'),
                'status' => SaleStatus::Completed,
                'price_level' => $priceLevel,
                'customer_id' => $customer?->id,
                'customer_name' => $customerName,
                'customer_phone' => $customerPhone,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => $paid,
                'credit' => $credit,
                'change' => $change,
                'cost_total' => 0,
                'notes' => $notes,
                'cashier_id' => $user?->getAuthIdentifier(),
                'cashier_name' => $user?->getAttribute('name'),
                'public_token' => Str::random(32),
                'completed_at' => $soldAt ?? now(),
            ]);
            if ($saleId !== null) {
                $sale->id = $saleId;
            }
            $sale->save();

            $cost = 0;
            foreach ($lines as $line) {
                $reference = new StockReference(MovementType::Sale, refType: 'sale', refId: $sale->id, note: $sale->reference());
                $issue = $this->stock->issue($branchId, $line['variant']->id, $line['qty'], $reference);
                if ($line['serials'] !== null) {
                    $this->serials->issue($branchId, $line['variant']->id, $line['serials'], $reference);
                }
                $sale->items()->create([
                    'tenant_id' => $tenantId,
                    'variant_id' => $line['variant']->id,
                    'name' => $line['variant']->displayName(),
                    'barcode' => $line['variant']->barcode,
                    'qty' => $line['qty'],
                    'unit_price' => $line['unit_price'],
                    'discount' => $line['discount'],
                    'line_total' => $line['line_total'],
                    'unit_cost' => $issue->unitCost(),
                    'serials' => $line['serials'],
                ]);
                $cost += $issue->totalCost();
            }
            $sale->update(['cost_total' => $cost]);

            foreach ($payments as $payment) {
                $sale->payments()->create(['tenant_id' => $tenantId, 'method' => $payment['method'], 'amount' => $payment['amount'], 'reference' => $payment['reference']]);
            }

            // Into the cashier's drawer (cash net of the change handed back); selling needs an open shift.
            $byMethod = [];
            foreach ($payments as $payment) {
                $byMethod[$payment['method']->value] = ($byMethod[$payment['method']->value] ?? 0) + $payment['amount'];
            }
            $taken = $byMethod;
            unset($taken[PaymentMethod::Credit->value]);
            if (isset($taken[PaymentMethod::Cash->value])) {
                $taken[PaymentMethod::Cash->value] -= $change;
            }
            if ($taken === [] || array_sum($taken) === 0) {
                // All on credit: nothing reaches the drawer, but the sale still belongs to a shift.
                if (! $this->drawer->hasOpenShift($branchId)) {
                    throw new DomainRuleException('افتح وردية الأول عشان الفلوس تتسجل في درجك.', 'shift_not_open', 409);
                }
            }
            foreach ($taken as $method => $amount) {
                $this->drawer->record($branchId, DrawerEntry::Sale, $method, $amount, 'sale', $sale->id, $sale->reference(), requireShift: true);
            }

            if ($customer !== null) {
                $credit > 0
                    ? $this->customers->chargeSale($customer->id, $credit, $sale->id, $sale->reference(), $branchId)
                    : $this->customers->touch($customer->id);
            }

            $this->events->record(new SaleCompleted(
                tenantId: $tenantId,
                saleId: $sale->id,
                branchId: $branchId,
                total: $total,
                cost: $cost,
                payments: $byMethod,
                change: $change,
                items: array_map(fn (array $l): array => ['variant_id' => $l['variant']->id, 'qty' => $l['qty']], $lines),
            ));

            $lineDiscounts = array_sum(array_column($lines, 'discount'));
            if ($discount + $lineDiscounts > 0 || $priceLevel !== PriceLevel::Retail) {
                $this->audit->record(
                    'sales.discounted',
                    'عمل خصم '.number_format(($discount + $lineDiscounts) / 100, 2)." ج على الفاتورة {$sale->reference()}"
                        .($priceLevel !== PriceLevel::Retail ? " بسعر {$priceLevel->label()}" : ''),
                    $sale,
                    ['invoice_discount' => $discount, 'line_discounts' => $lineDiscounts, 'price_level' => $priceLevel->value],
                );
            }

            if ($keptPrices !== []) {
                $this->audit->record(
                    'sales.offline_price',
                    "الفاتورة {$sale->reference()} اتعملت أوفلاين واتسجلت بالسعر اللي العميل دفعه: "
                        .implode('، ', array_map(fn (array $k) => "{$k['name']} بـ ".number_format($k['paid'] / 100, 2).' بدل '.number_format($k['current'] / 100, 2), $keptPrices)),
                    $sale,
                    ['lines' => $keptPrices],
                );
            }

            return $sale;
        });
    }
}
