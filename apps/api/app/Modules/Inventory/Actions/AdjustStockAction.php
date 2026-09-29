<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Models\StockLevel;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * Brings the books in line with the shelf: either the counted quantity (a stocktake) or a
 * signed change (5 broken screens: -5). Increases enter at the average cost unless one is given.
 */
final class AdjustStockAction
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly VariantCatalog $catalog,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  list<array{variant_id: string, counted?: int|null, delta?: int|null, unit_cost?: int|null}>  $items
     * @return list<array{variant_id: string, name: string, before: int, after: int, delta: int}>
     */
    public function handle(string $tenantId, string $branchId, AdjustmentReason $reason, ?string $note, array $items): array
    {
        $variants = $this->catalog->find(array_column($items, 'variant_id'));

        return DB::transaction(function () use ($tenantId, $branchId, $reason, $note, $items, $variants): array {
            $changes = [];

            foreach ($items as $item) {
                $variant = $variants[$item['variant_id']] ?? throw new DomainRuleException('فيه صنف مش موجود.', 'variant_not_found', 404);

                // Read under the ledger's lock order: the ledger locks the balance row itself,
                // so take the current quantity from a locked read too.
                $before = (int) StockLevel::query()
                    ->where('branch_id', $branchId)->where('variant_id', $variant->id)
                    ->lockForUpdate()->value('qty');

                $delta = isset($item['counted']) ? $item['counted'] - $before : (int) ($item['delta'] ?? 0);
                if ($delta === 0) {
                    continue;
                }

                $reference = new StockReference(MovementType::Adjustment, reason: $reason->value, note: $note);

                if ($delta > 0) {
                    $cost = $item['unit_cost'] ?? (int) StockLevel::query()->where('branch_id', $branchId)->where('variant_id', $variant->id)->value('avg_cost');
                    $this->ledger->receive($branchId, $variant->id, $delta, $cost, $reference);
                } else {
                    $this->ledger->issue($branchId, $variant->id, -$delta, $reference);
                }

                $changes[] = ['variant_id' => $variant->id, 'name' => $variant->displayName(), 'before' => $before, 'after' => $before + $delta, 'delta' => $delta];
            }

            if ($changes !== []) {
                $this->audit->record(
                    'inventory.adjusted',
                    "سوّى المخزون ({$reason->label()}) لـ ".count($changes).' صنف',
                    properties: ['branch_id' => $branchId, 'reason' => $reason->value, 'note' => $note, 'changes' => $changes],
                    tenantId: $tenantId,
                );
            }

            return $changes;
        });
    }
}
