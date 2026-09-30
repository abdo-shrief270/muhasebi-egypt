<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Actions;

use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\SupplierReturns\Contracts\ReturnReason;
use App\Modules\SupplierReturns\Models\BinItem;
use App\Modules\SupplierReturns\Support\SourceDetector;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * What can happen to a unit while it waits in the bin: its reason / source corrected, back to
 * sellable stock (it was fine after all), or written off (not worth sending back).
 */
final class BinItemActions
{
    public function __construct(
        private readonly StockLedger $stock,
        private readonly SerialRegistry $serials,
        private readonly SourceDetector $detector,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array{type: string, id: string}|null  $source
     */
    public function update(BinItem $item, ?ReturnReason $reason, ?string $note, ?array $source): BinItem
    {
        return DB::transaction(function () use ($item, $reason, $note, $source): BinItem {
            $item = $this->lockInBin($item);
            if ($reason !== null) {
                $item->reason = $reason;
            }
            if ($note !== null) {
                $item->note = trim($note) !== '' ? trim($note) : null;
            }
            if ($item->reason === ReturnReason::Other && $item->note === null) {
                throw new DomainRuleException('اكتب السبب في الملاحظة.', 'note_required');
            }
            if ($source !== null) {
                $item->fill($this->detector->manual($source['type'], $source['id'])->columns());
            }
            $item->save();

            return $item;
        });
    }

    public function restock(BinItem $item): BinItem
    {
        return DB::transaction(function () use ($item): BinItem {
            $item = $this->lockInBin($item);
            $reference = new StockReference(MovementType::ReturnsBinBack, refType: 'returns_bin', refId: $item->id, note: 'رجع من سلة المرتجعات');
            $this->stock->restore($item->branch_id, $item->variant_id, $item->qty, $item->unit_cost, $item->lot_id, $reference);
            if ($item->serial !== null) {
                $this->serials->putBack($item->branch_id, $item->variant_id, [$item->serial], $reference);
            }
            $item->update(['status' => BinItem::SETTLED, 'outcome' => 'restocked', 'accepted_qty' => 0, 'settled_at' => now()]);
            $this->audit->record('supplier_returns.restocked', "رجّع {$item->qty} × {$item->variant_name} من سلة المرتجعات للمخزون", $item);

            return $item;
        });
    }

    public function writeOff(BinItem $item): BinItem
    {
        return DB::transaction(function () use ($item): BinItem {
            $item = $this->lockInBin($item);
            if ($item->serial !== null) {
                $reference = new StockReference(MovementType::WriteOff, refType: 'returns_bin', refId: $item->id, note: 'إعدام من سلة المرتجعات');
                $this->serials->release($item->branch_id, $item->variant_id, [$item->serial], $reference);
            }
            $item->update(['status' => BinItem::SETTLED, 'outcome' => 'written_off', 'accepted_qty' => 0, 'settled_at' => now()]);
            $this->audit->record(
                'supplier_returns.written_off',
                "أعدم {$item->qty} × {$item->variant_name} من سلة المرتجعات (خسارة ".number_format($item->value() / 100, 2).' ج)',
                $item,
                ['qty' => $item->qty, 'value' => $item->value(), 'serial' => $item->serial],
            );

            return $item;
        });
    }

    private function lockInBin(BinItem $item): BinItem
    {
        $item = BinItem::query()->lockForUpdate()->findOrFail($item->id);
        if ($item->status !== BinItem::IN_BIN) {
            throw new DomainRuleException('القطعة دي مش في السلة (على إذن مرتجع أو اتسوّت).', 'not_in_bin');
        }

        return $item;
    }
}
