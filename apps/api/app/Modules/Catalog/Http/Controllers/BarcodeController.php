<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\GenerateBarcodesAction;
use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Barcodes for labels: pick variants (search or by id) and give the ones without a barcode one.
 */
final class BarcodeController
{
    /** ?q= or ?ids[]= — variants with what a label prints (name, barcode, price). */
    public function variants(Request $request, VariantCatalog $catalog): JsonResponse
    {
        $ids = array_values(array_filter((array) $request->query('ids', []), 'is_string'));
        $items = $ids !== []
            ? array_values($catalog->find($ids))
            : $catalog->search($request->filled('q') ? (string) $request->query('q') : null, null, null, 1, 30)['items'];

        return response()->json(['data' => array_map(fn (VariantSummary $v): array => $v->toArray(), $items)]);
    }

    public function generate(Request $request, GenerateBarcodesAction $action, CurrentTenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'variant_ids' => ['required', 'array', 'min:1', 'max:500'],
            'variant_ids.*' => ['uuid', 'distinct'],
        ]);

        return response()->json(['data' => $action->handle($tenant->idOrFail(), $validated['variant_ids'])]);
    }
}
