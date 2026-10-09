<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\PriceLevel;
use App\Modules\Sales\Enums\SaleStatus;
use App\Modules\Sales\Models\SaleItem;
use App\Support\Tenancy\CurrentBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

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

    /**
     * What this customer paid for these items before (any branch), newest first, at most 3 each:
     * the cashier's hint «آخر مرة اشتراه بـ …». The unit price after the line's discount; lines
     * returned in full and fully refunded invoices don't count.
     */
    public function lastPrices(Request $request): JsonResponse
    {
        $data = $request->validate([
            'customer_id' => ['required', 'uuid'],
            'variant_ids' => ['required', 'array', 'max:100'],
            'variant_ids.*' => ['uuid'],
        ]);

        $rows = SaleItem::query()
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->where('sales.customer_id', $data['customer_id'])
            ->where('sales.status', '<>', SaleStatus::Refunded->value)
            ->whereIn('sale_items.variant_id', array_values(array_unique($data['variant_ids'])))
            ->whereColumn('sale_items.returned_qty', '<', 'sale_items.qty')
            ->orderByDesc('sales.completed_at')
            ->limit(300)
            ->get(['sale_items.variant_id', 'sale_items.qty', 'sale_items.unit_price', 'sale_items.line_total', 'sales.id as sale_id', 'sales.number', 'sales.completed_at']);

        $out = [];
        foreach ($rows as $r) {
            $list = $out[$r->variant_id] ?? [];
            if (count($list) >= 3) {
                continue;
            }
            $list[] = [
                'price' => intdiv((int) $r->line_total, max(1, (int) $r->qty)),
                'list_price' => (int) $r->unit_price,
                'qty' => (int) $r->qty,
                'sale_id' => $r->sale_id,
                'reference' => 'INV-'.str_pad((string) $r->number, 6, '0', STR_PAD_LEFT),
                'at' => Carbon::parse($r->completed_at)->toIso8601String(),
            ];
            $out[$r->variant_id] = $list;
        }

        return response()->json(['data' => (object) $out]);
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'payment_methods' => array_map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()], PaymentMethod::cases()),
            'price_levels' => array_map(fn (PriceLevel $l) => ['value' => $l->value, 'label' => $l->label()], PriceLevel::cases()),
        ]]);
    }
}
