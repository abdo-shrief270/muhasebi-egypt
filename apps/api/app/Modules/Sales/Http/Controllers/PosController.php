<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Inventory\Contracts\SerialRegistry;
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
    private const CATALOG_PAGE = 500;

    public function __construct(
        private readonly VariantCatalog $catalog,
        private readonly StockLedger $stock,
        private readonly SerialRegistry $serials,
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

    /**
     * The whole sellable catalog of this branch, page by page (?page=), for the POS to keep on the
     * device and keep selling when the internet drops: prices, barcode, SKU, category, stock here,
     * and the IMEIs / serials in stock here for products that track them.
     */
    public function catalog(Request $request): JsonResponse
    {
        $page = $this->catalog->search(q: null, categoryId: null, onlyIds: null, page: max(1, $request->integer('page', 1)), perPage: self::CATALOG_PAGE);
        $items = $page['items'];
        $ids = array_map(fn (VariantSummary $v) => $v->id, $items);
        $branchId = $this->branch->idOrFail();
        $qty = $this->stock->quantities($branchId, $ids);
        $serials = $this->serials->inStock($branchId, array_values(array_map(fn (VariantSummary $v) => $v->id, array_filter($items, fn (VariantSummary $v) => $v->trackSerial))));

        return response()->json([
            'data' => array_map(fn (VariantSummary $v): array => [
                ...$v->toArray(),
                'qty' => $qty[$v->id] ?? 0,
                'serials' => $v->trackSerial ? ($serials[$v->id] ?? []) : null,
            ], $items),
            'meta' => ['current_page' => $page['page'], 'last_page' => $page['last_page'], 'per_page' => $page['per_page'], 'total' => $page['total'], 'generated_at' => now()->toIso8601String()],
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
