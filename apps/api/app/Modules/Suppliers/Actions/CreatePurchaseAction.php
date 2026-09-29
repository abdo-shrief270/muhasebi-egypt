<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Actions;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialCount;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\Suppliers\Enums\PaymentMethod;
use App\Modules\Suppliers\Enums\SupplierTransactionType;
use App\Modules\Suppliers\Events\PurchaseReceived;
use App\Modules\Suppliers\Models\Purchase;
use App\Modules\Suppliers\Models\Supplier;
use App\Modules\Suppliers\Support\SupplierAccount;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\DB;

/**
 * Posts a supplier invoice in one go: stock comes in (one lot per line, at the cost after the
 * invoice discount), the total goes on the supplier's account, and what was paid comes off it.
 */
final class CreatePurchaseAction
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly SerialRegistry $serials,
        private readonly VariantCatalog $catalog,
        private readonly SupplierAccount $account,
        private readonly CashDrawer $drawer,
        private readonly DocumentNumbers $numbers,
        private readonly EventRecorder $events,
        private readonly Auditor $audit,
        private readonly Auth $auth,
    ) {}

    /**
     * @param  list<array{variant_id: string, qty: int, unit_cost: int, serials?: list<string>|null}>  $items  costs in piasters; serials for products that track them
     */
    public function handle(
        string $tenantId,
        string $branchId,
        string $supplierId,
        array $items,
        string $invoiceDate,
        ?string $supplierInvoiceNo = null,
        int $discount = 0,
        int $paid = 0,
        ?PaymentMethod $paymentMethod = null,
        ?string $notes = null,
        bool $fromDrawer = true,
    ): Purchase {
        $supplier = Supplier::query()->find($supplierId) ?? throw new DomainRuleException('المورد مش موجود.', 'supplier_not_found', 404);
        if (! $supplier->is_active) {
            throw new DomainRuleException("المورد «{$supplier->name}» موقوف.", 'supplier_inactive');
        }

        $variantIds = array_column($items, 'variant_id');
        $variants = $this->catalog->find($variantIds);
        if ($missing = array_diff($variantIds, array_keys($variants))) {
            throw new DomainRuleException('فيه صنف في الفاتورة مش موجود.', 'variant_not_found', 404, ['variant_ids' => array_values($missing)]);
        }

        foreach ($items as $i => $item) {
            $variant = $variants[$item['variant_id']];
            $serials = SerialCount::check($variant->displayName(), $variant->trackSerial, $item['qty'], $item['serials'] ?? null, $variant->id);
            $items[$i]['serials'] = $serials === null ? null : $this->serials->normalize($serials);
        }

        $subtotal = array_sum(array_map(fn (array $i): int => $i['qty'] * $i['unit_cost'], $items));
        if ($discount > $subtotal) {
            throw new DomainRuleException('الخصم أكبر من إجمالي الفاتورة.', 'discount_too_large');
        }
        $total = $subtotal - $discount;
        if ($paid > $total) {
            throw new DomainRuleException('المدفوع أكبر من إجمالي الفاتورة.', 'overpaid');
        }
        if ($paid > 0 && $paymentMethod === null) {
            throw new DomainRuleException('اختار طريقة الدفع.', 'payment_method_required');
        }

        return DB::transaction(function () use ($tenantId, $branchId, $supplier, $items, $invoiceDate, $supplierInvoiceNo, $discount, $paid, $paymentMethod, $notes, $subtotal, $total, $fromDrawer): Purchase {
            $user = $this->auth->guard('sanctum')->user();
            $previousCosts = $this->stock->averageCosts($branchId, array_column($items, 'variant_id'));

            $purchase = Purchase::create([
                'tenant_id' => $tenantId,
                'branch_id' => $branchId,
                'supplier_id' => $supplier->id,
                'number' => $this->numbers->next($tenantId, 'purchase'),
                'supplier_invoice_no' => $supplierInvoiceNo,
                'invoice_date' => $invoiceDate,
                'subtotal' => $subtotal,
                'discount' => $discount,
                'total' => $total,
                'paid' => $paid,
                'payment_method' => $paid > 0 ? $paymentMethod : null,
                'notes' => $notes,
                'created_by' => $user?->getAuthIdentifier(),
                'created_by_name' => $user?->getAttribute('name'),
            ]);

            $costIncreases = [];
            foreach ($items as $item) {
                // The invoice discount lowers what each unit really cost, in proportion.
                $net = $subtotal > 0 ? intdiv($item['unit_cost'] * $total + intdiv($subtotal, 2), $subtotal) : 0;
                $reference = new StockReference(MovementType::Purchase, refType: 'purchase', refId: $purchase->id, note: "فاتورة شراء {$purchase->reference()} من {$supplier->name}");
                $lotId = $this->stock->receive($branchId, $item['variant_id'], $item['qty'], $net, $reference);
                if ($item['serials'] !== null) {
                    $this->serials->receive($branchId, $item['variant_id'], $item['serials'], $reference);
                }

                $line = $purchase->items()->create([
                    'tenant_id' => $tenantId,
                    'variant_id' => $item['variant_id'],
                    'lot_id' => $lotId,
                    'qty' => $item['qty'],
                    'unit_cost' => $item['unit_cost'],
                    'net_unit_cost' => $net,
                    'line_total' => $item['qty'] * $item['unit_cost'],
                    'previous_cost' => $previousCosts[$item['variant_id']] ?? null,
                    'serials' => $item['serials'],
                ]);

                if ($line->costIncreased()) {
                    $costIncreases[] = ['variant_id' => $line->variant_id, 'previous_cost' => (int) $line->previous_cost, 'new_cost' => $net];
                }
            }

            $this->account->post($supplier->id, SupplierTransactionType::Purchase, $total, $purchase, note: $purchase->reference());
            if ($paid > 0) {
                $this->account->post($supplier->id, SupplierTransactionType::Payment, -$paid, $purchase, $paymentMethod, "مدفوع مع الفاتورة {$purchase->reference()}");
                // Paid out of the drawer unless it came from elsewhere (the owner's pocket, the safe).
                if ($fromDrawer && ($method = $paymentMethod?->drawerMethod()) !== null) {
                    $this->drawer->record($branchId, DrawerEntry::SupplierPayment, $method, -$paid, 'purchase', $purchase->id, "{$purchase->reference()} — {$supplier->name}");
                }
            }

            $this->events->record(new PurchaseReceived(
                tenantId: $tenantId,
                purchaseId: $purchase->id,
                branchId: $branchId,
                supplierId: $supplier->id,
                total: $total,
                paid: $paid,
                paymentMethod: $paid > 0 ? $paymentMethod?->value : null,
                costIncreases: $costIncreases,
            ));

            $this->audit->record(
                'purchases.created',
                "سجّل فاتورة شراء {$purchase->reference()} من «{$supplier->name}» بـ ".number_format($total / 100, 2).' ج',
                $purchase,
                ['items' => count($items), 'total' => $total, 'paid' => $paid, 'cost_increases' => count($costIncreases)],
            );

            return $purchase;
        });
    }
}
