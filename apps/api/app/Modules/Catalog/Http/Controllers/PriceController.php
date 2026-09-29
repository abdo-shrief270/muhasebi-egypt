<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\BulkPriceAction;
use App\Modules\Catalog\Http\Requests\BulkPriceRequest;
use App\Modules\Catalog\Models\PriceChange;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Support\PriceRule;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;

/** Bulk price edits (preview, then apply) and a product's price history. */
final class PriceController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly CurrentBranch $branch,
    ) {}

    public function preview(BulkPriceRequest $request, BulkPriceAction $action): JsonResponse
    {
        $result = $action->preview(
            $this->branch->idOrFail(),
            $request->filters(),
            $request->priceRule(),
            (bool) $request->user()?->can('products.view_cost'),
        );

        return response()->json(['data' => [...$result, 'description' => $request->priceRule()->describe()]]);
    }

    public function apply(BulkPriceRequest $request, BulkPriceAction $action): JsonResponse
    {
        return response()->json(['data' => $action->apply(
            $this->tenant->idOrFail(),
            $this->branch->idOrFail(),
            $request->filters(),
            $request->priceRule(),
            $request->excluded(),
        )]);
    }

    public function history(Product $product): JsonResponse
    {
        $variants = $product->variants()->pluck('name', 'id');

        $changes = PriceChange::query()
            ->whereIn('variant_id', $variants->keys())
            ->orderByDesc('seq')
            ->limit(200)
            ->get()
            ->map(fn (PriceChange $c) => [
                'id' => $c->id,
                'variant' => $variants->get($c->variant_id),
                'field' => $c->field,
                'field_label' => PriceRule::label($c->field),
                'old_price' => $c->old_price,
                'new_price' => $c->new_price,
                'source' => $c->source,
                'user_name' => $c->user_name,
                'created_at' => $c->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => $changes]);
    }
}
