<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Support\Tenancy\CurrentBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * «بكام ده؟»: a customer asks, the shop scans or types — prices, and where it's in stock.
 * Wholesale / technician prices for whoever may sell at them, cost with products.view_cost,
 * stock per branch with inventory.view.
 */
final class PriceCheckController
{
    public function __construct(
        private readonly VariantCatalog $catalog,
        private readonly StockLedger $stock,
        private readonly CurrentBranch $branch,
        private readonly BranchDirectory $branches,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        if (mb_strlen($q) < 2) {
            return response()->json(['data' => []]);
        }

        $user = $request->user() ?? abort(401);
        $items = $this->catalog->search($q, null, null, 1, 8)['items'];
        usort($items, fn (VariantSummary $a, VariantSummary $b): int => (int) ($b->barcode === $q) <=> (int) ($a->barcode === $q));
        $ids = array_map(fn (VariantSummary $v) => $v->id, $items);

        $current = $this->branch->idOrFail();
        $levels = $user->can('sales.discount') || $user->can('products.manage');
        $costs = $user->can('products.view_cost') ? $this->stock->averageCosts($current, $ids) : null;
        $stock = [];
        if ($user->can('inventory.view')) {
            foreach ($this->branches->accessibleBranches($user) as $branchId => $name) {
                $stock[$branchId] = ['name' => $name, 'qty' => $this->stock->quantities($branchId, $ids)];
            }
        }

        return response()->json(['data' => array_map(fn (VariantSummary $v): array => [
            'id' => $v->id,
            'product_id' => $v->productId,
            'display_name' => $v->displayName(),
            'barcode' => $v->barcode,
            'category' => $v->categoryName,
            'quality_label' => $v->qualityLabel,
            'is_active' => $v->isActive,
            'track_serial' => $v->trackSerial,
            'exact_barcode' => $v->barcode === $q,
            'prices' => [
                'retail' => $v->priceRetail,
                'wholesale' => $levels ? $v->priceWholesale : null,
                'technician' => $levels ? $v->priceTechnician : null,
            ],
            'cost' => $costs === null ? null : ($costs[$v->id] ?? null),
            'stock' => $stock === [] ? null : array_values(array_map(fn (string $branchId, array $b): array => [
                'branch_id' => $branchId,
                'name' => $b['name'],
                'qty' => $b['qty'][$v->id] ?? 0,
                'current' => $branchId === $current,
            ], array_keys($stock), $stock)),
        ], $items)]);
    }
}
