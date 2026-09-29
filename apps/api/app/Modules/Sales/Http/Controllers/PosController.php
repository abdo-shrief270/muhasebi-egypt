<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\PriceLevel;
use App\Support\Tenancy\CurrentBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * What the cashier screen needs: sellable items with their stock in this branch.
 */
final class PosController
{
    public function __construct(
        private readonly VariantCatalog $catalog,
        private readonly StockLedger $stock,
        private readonly CurrentBranch $branch,
    ) {}

    /** ?q= &category_id= &page= — an exact barcode match comes first. */
    public function items(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $page = $this->catalog->search(
            q: $q === '' ? null : $q,
            categoryId: $request->filled('category_id') ? $request->integer('category_id') : null,
            // ?ids[]= fetches given variants (added from the price check).
            onlyIds: $request->has('ids') ? array_values(array_filter((array) $request->query('ids'), 'is_string')) : null,
            page: max(1, $request->integer('page', 1)),
            perPage: 48,
        );
        $items = $page['items'];
        usort($items, fn (VariantSummary $a, VariantSummary $b): int => (int) ($b->barcode === $q) <=> (int) ($a->barcode === $q));
        $qty = $this->stock->quantities($this->branch->idOrFail(), array_map(fn (VariantSummary $v) => $v->id, $items));

        return response()->json([
            'data' => array_map(fn (VariantSummary $v): array => [
                ...$v->toArray(),
                'qty' => $qty[$v->id] ?? 0,
                'exact_barcode' => $q !== '' && $v->barcode === $q,
            ], $items),
            'meta' => ['current_page' => $page['page'], 'last_page' => $page['last_page'], 'total' => $page['total']],
        ]);
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'payment_methods' => array_map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()], PaymentMethod::cases()),
            'price_levels' => array_map(fn (PriceLevel $l) => ['value' => $l->value, 'label' => $l->label()], PriceLevel::cases()),
        ]]);
    }
}
