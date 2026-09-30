<?php

declare(strict_types=1);

namespace App\Modules\Sales;

use App\Modules\Sales\Contracts\UnitSale;
use App\Modules\Sales\Contracts\UnitSales;
use App\Modules\Sales\Models\SaleItem;

final class UnitSalesService implements UnitSales
{
    public function lastSales(array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $found = [];
        SaleItem::query()
            ->with('sale')
            ->whereIn('variant_id', array_values(array_unique($variantIds)))
            ->whereColumn('returned_qty', '<', 'qty')
            ->orderBy('id')
            ->get()
            ->each(function (SaleItem $item) use (&$found): void {
                $sale = $item->sale;
                // The line's share of the invoice discount comes off, like a refund would.
                $unit = $sale->subtotal > 0
                    ? intdiv($item->line_total * $sale->total + intdiv($sale->subtotal * $item->qty, 2), $sale->subtotal * $item->qty)
                    : 0;
                $found[$item->variant_id] = new UnitSale($sale->id, $sale->reference(), $sale->completed_at, $unit, $sale->customer_name);
            });

        return $found;
    }
}
