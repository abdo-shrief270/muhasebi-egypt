<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Actions;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Inventory\Contracts\MovementType;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockReference;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/**
 * The stock a branch starts with. Only for variants that never moved in that branch —
 * after that, differences are counted and adjusted (AdjustStockAction).
 */
final class SetOpeningStockAction
{
    public function __construct(
        private readonly StockLedger $ledger,
        private readonly VariantCatalog $catalog,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  list<array{variant_id: string, qty: int, unit_cost: int}>  $items
     */
    public function handle(string $tenantId, string $branchId, array $items): int
    {
        $variants = $this->catalog->find(array_column($items, 'variant_id'));
        $moved = $this->ledger->variantsWithHistory(array_column($items, 'variant_id'), $branchId);

        foreach ($items as $i => $item) {
            $variant = $variants[$item['variant_id']] ?? throw new DomainRuleException('فيه صنف مش موجود.', 'variant_not_found', 404);
            if (in_array($item['variant_id'], $moved, true)) {
                throw new DomainRuleException(
                    "«{$variant->displayName()}» ليه حركات في الفرع ده بالفعل. استخدم الجرد بدل الرصيد الافتتاحي.",
                    'opening_after_movements',
                    context: ['index' => $i],
                );
            }
        }

        return DB::transaction(function () use ($tenantId, $branchId, $items, $variants): int {
            $units = 0;
            foreach ($items as $item) {
                $this->ledger->receive($branchId, $item['variant_id'], $item['qty'], $item['unit_cost'], new StockReference(MovementType::Opening));
                $units += $item['qty'];
            }

            $this->audit->record(
                'inventory.opening',
                'سجّل رصيد افتتاحي لـ '.count($items).' صنف ('.$units.' قطعة)',
                properties: ['branch_id' => $branchId, 'items' => array_map(fn (array $i): array => [
                    'variant' => $variants[$i['variant_id']]->displayName(), 'qty' => $i['qty'], 'unit_cost' => $i['unit_cost'],
                ], $items)],
                tenantId: $tenantId,
            );

            return $units;
        });
    }
}
