<?php

declare(strict_types=1);

namespace App\Modules\Sales\Actions;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Events\SaleRefunded;
use App\Modules\Sales\Models\Sale;
use App\Modules\Sales\Models\SaleItem;
use App\Modules\Sales\Models\SaleReturn;
use App\Modules\Sales\Models\SaleReturnItem;
use App\Support\Audit\Auditor;
use App\Support\Events\EventRecorder;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\FeatureAccess;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Takes items back from a customer. Each unit is refunded at what it was really paid (its line
 * after the invoice discount's share). Sound units go back into stock at their original cost;
 * damaged ones don't: they're a loss, or — with supplier returns on — they go to the returns bin
 * (SaleRefunded carries them). The money leaves the
 * refunding cashier's drawer, or — refunded as credit (آجل) — comes off the customer's account.
 */
final class CreateSaleReturnAction
{
    public function __construct(
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
     * @param  list<array{sale_item_id: int, qty: int, restock: bool, serials?: list<string>|null, defect_reason?: string|null}>  $lines  serials: which units, for lines sold with serials
     */
    public function handle(string $tenantId, string $branchId, Sale $sale, array $lines, PaymentMethod $refundMethod, ?string $reason): SaleReturn
    {
        // The owner's return window: no returns more than N days after the sale.
        if ($this->features->enabled('sales.return_window')) {
            $days = (int) $this->features->setting('sales.return_window');
            if ($sale->completed_at->copy()->addDays($days)->isPast()) {
                throw new DomainRuleException("الفاتورة دي عدّى عليها أكتر من {$days} يوم، والمرتجع مسموح خلال {$days} يوم بس.", 'return_window_passed', context: ['days' => $days]);
            }
        }
        if ($refundMethod === PaymentMethod::Credit && $sale->customer_id === null) {
            throw new DomainRuleException('الفاتورة دي مش على عميل، فمينفعش المرتجع يتخصم من حسابه.', 'credit_needs_customer');
        }

        return DB::transaction(function () use ($tenantId, $branchId, $sale, $lines, $refundMethod, $reason): SaleReturn {
            $sale = Sale::query()->lockForUpdate()->findOrFail($sale->id);
            /** @var Collection<int, SaleItem> $items */
            $items = $sale->items()->get()->keyBy('id');

            foreach ($lines as $line) {
                $item = $items->get($line['sale_item_id']) ?? throw new DomainRuleException('فيه سطر مش تبع الفاتورة دي.', 'sale_item_not_found', 404);
                $left = $item->qty - $item->returned_qty;
                if ($line['qty'] > $left) {
                    throw new DomainRuleException("مينفعش ترجّع أكتر من {$left} من «{$item->name}».", 'return_exceeds_sale', context: ['sale_item_id' => $item->id, 'max' => $left]);
                }
            }

            // Lines sold with serials: which units come back (all that are left when not said).
            $serialsOf = [];
            foreach ($lines as $line) {
                $item = $items->get($line['sale_item_id']);
                if ($item->serials === null) {
                    continue;
                }
                $alreadyBack = SaleReturnItem::query()->where('sale_item_id', $item->id)->pluck('serials')->flatten()->all();
                $remaining = array_values(array_diff($item->serials, $alreadyBack));
                $chosen = $line['serials'] !== null && $line['serials'] !== []
                    ? $this->serials->normalize($line['serials'])
                    : (count($remaining) === $line['qty'] ? $remaining : []);
                if (count($chosen) !== $line['qty'] || array_diff($chosen, $remaining) !== []) {
                    throw new DomainRuleException("اختار سيريالات «{$item->name}» اللي راجعة ({$line['qty']}).", 'serials_required', context: ['sale_item_id' => $item->id, 'serials' => $remaining]);
                }
                $serialsOf[$item->id] = $chosen;
            }

            $user = $this->auth->guard('sanctum')->user();
            $return = SaleReturn::create([
                'tenant_id' => $tenantId,
                'branch_id' => $sale->branch_id,
                'sale_id' => $sale->id,
                'number' => $this->numbers->next($tenantId, 'sale_return'),
                'total' => 0,
                'cost' => 0,
                'refund_method' => $refundMethod,
                'reason' => $reason,
                'created_by' => $user?->getAuthIdentifier(),
                'created_by_name' => $user?->getAttribute('name'),
            ]);

            $total = 0;
            $cost = 0;
            $damaged = [];
            foreach ($lines as $line) {
                /** @var SaleItem $item */
                $item = $items->get($line['sale_item_id']);
                // What one unit was really paid: its line, less its share of the invoice discount.
                $unitRefund = $sale->subtotal > 0
                    ? intdiv($item->line_total * $sale->total + intdiv($sale->subtotal * $item->qty, 2), $sale->subtotal * $item->qty)
                    : 0;

                $reference = new StockReference(MovementType::SaleReturn, refType: 'sale_return', refId: $return->id, note: "{$return->reference()} من {$sale->reference()}");
                if ($line['restock']) {
                    $this->stock->receive($sale->branch_id, $item->variant_id, $line['qty'], $item->unit_cost, $reference);
                }
                if (isset($serialsOf[$item->id])) {
                    $this->serials->takeBack($sale->branch_id, $item->variant_id, $serialsOf[$item->id], $reference, $line['restock']);
                }

                $return->items()->create([
                    'tenant_id' => $tenantId,
                    'sale_item_id' => $item->id,
                    'variant_id' => $item->variant_id,
                    'qty' => $line['qty'],
                    'unit_refund' => $unitRefund,
                    'line_total' => $unitRefund * $line['qty'],
                    'restocked' => $line['restock'],
                    'serials' => $serialsOf[$item->id] ?? null,
                ]);
                $item->increment('returned_qty', $line['qty']);
                if (! $line['restock']) {
                    $damaged[] = [
                        'variant_id' => $item->variant_id,
                        'qty' => $line['qty'],
                        'unit_cost' => $item->unit_cost,
                        'serials' => $serialsOf[$item->id] ?? null,
                        'reason' => $line['defect_reason'] ?? null,
                    ];
                }
                $total += $unitRefund * $line['qty'];
                // Restocked units stop counting as sold cost; damaged ones stay a cost (a loss).
                $cost += $line['restock'] ? $item->unit_cost * $line['qty'] : 0;
            }

            $return->update(['total' => $total, 'cost' => $cost]);

            if ($refundMethod === PaymentMethod::Credit) {
                $this->customers->creditReturn((string) $sale->customer_id, $total, $return->id, $return->reference(), $branchId);
            } else {
                $this->drawer->record($branchId, DrawerEntry::SaleRefund, $refundMethod->value, -$total, 'sale_return', $return->id, "{$return->reference()} من {$sale->reference()}");
            }

            $allBack = $items->every(fn (SaleItem $i) => $i->fresh()?->returned_qty === $i->qty);
            $sale->update([
                'refunded' => $sale->refunded + $total,
                'refunded_cost' => $sale->refunded_cost + $cost,
                'status' => $allBack ? SaleStatus::Refunded : SaleStatus::PartiallyRefunded,
            ]);

            $this->events->record(new SaleRefunded($tenantId, $sale->id, $return->id, $sale->branch_id, $total, $refundMethod->value, $damaged, $return->reference(), $sale->reference()));
            $this->audit->record(
                'sales.refunded',
                "عمل مرتجع {$return->reference()} بـ ".number_format($total / 100, 2)." ج من الفاتورة {$sale->reference()}",
                $return,
                ['sale_id' => $sale->id, 'total' => $total, 'refund_method' => $refundMethod->value],
            );

            return $return;
        });
    }
}
