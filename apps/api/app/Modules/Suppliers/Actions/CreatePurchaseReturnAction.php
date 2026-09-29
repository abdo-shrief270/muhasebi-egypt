<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Actions;

use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\Suppliers\Enums\SupplierTransactionType;
use App\Modules\Suppliers\Models\Purchase;
use App\Modules\Suppliers\Models\PurchaseItem;
use App\Modules\Suppliers\Models\PurchaseReturn;
use App\Modules\Suppliers\Support\SupplierAccount;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Sends part of a purchase back to its supplier: the units leave stock (from the lot they came in
 * with, when it still has them) at what they cost, and that amount comes off the supplier's account.
 */
final class CreatePurchaseReturnAction
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly SupplierAccount $account,
        private readonly DocumentNumbers $numbers,
        private readonly Auditor $audit,
        private readonly Auth $auth,
    ) {}

    /**
     * @param  list<array{purchase_item_id: int, qty: int}>  $lines
     */
    public function handle(string $tenantId, Purchase $purchase, array $lines, ?string $notes): PurchaseReturn
    {
        return DB::transaction(function () use ($tenantId, $purchase, $lines, $notes): PurchaseReturn {
            /** @var Collection<int, PurchaseItem> $items */
            $items = $purchase->items()->lockForUpdate()->get()->keyBy('id');

            foreach ($lines as $line) {
                $item = $items->get($line['purchase_item_id']) ?? throw new DomainRuleException('فيه سطر مش تبع الفاتورة دي.', 'purchase_item_not_found', 404);
                $left = $item->qty - $item->returned_qty;
                if ($line['qty'] > $left) {
                    throw new DomainRuleException("مينفعش ترجّع أكتر من {$left} من السطر ده.", 'return_exceeds_purchase', context: ['purchase_item_id' => $item->id, 'max' => $left]);
                }
            }

            $user = $this->auth->guard('sanctum')->user();
            $return = PurchaseReturn::create([
                'tenant_id' => $tenantId,
                'branch_id' => $purchase->branch_id,
                'supplier_id' => $purchase->supplier_id,
                'purchase_id' => $purchase->id,
                'number' => $this->numbers->next($tenantId, 'purchase_return'),
                'total' => 0,
                'notes' => $notes,
                'created_by' => $user?->getAuthIdentifier(),
                'created_by_name' => $user?->getAttribute('name'),
            ]);

            $total = 0;
            foreach ($lines as $line) {
                /** @var PurchaseItem $item */
                $item = $items->get($line['purchase_item_id']);

                $this->stock->issue($purchase->branch_id, $item->variant_id, $line['qty'], new StockReference(
                    MovementType::SupplierReturn,
                    refType: 'purchase_return',
                    refId: $return->id,
                    note: "مرتجع {$return->reference()} من فاتورة {$purchase->reference()}",
                ), fromLotId: $item->lot_id);

                $lineTotal = $line['qty'] * $item->net_unit_cost;
                $return->items()->create([
                    'tenant_id' => $tenantId,
                    'purchase_item_id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'qty' => $line['qty'],
                    'unit_cost' => $item->net_unit_cost,
                    'line_total' => $lineTotal,
                ]);
                $item->increment('returned_qty', $line['qty']);
                $total += $lineTotal;
            }

            $return->update(['total' => $total]);
            $purchase->increment('returned', $total);
            $this->account->post($purchase->supplier_id, SupplierTransactionType::PurchaseReturn, -$total, $return, note: "{$return->reference()} من {$purchase->reference()}");

            $this->audit->record(
                'purchases.returned',
                'رجّع بضاعة بـ '.number_format($total / 100, 2)." ج من فاتورة {$purchase->reference()} ({$return->reference()})",
                $return,
                ['purchase_id' => $purchase->id, 'total' => $total],
            );

            return $return;
        });
    }
}
