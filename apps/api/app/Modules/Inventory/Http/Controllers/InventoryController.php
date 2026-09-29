<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Inventory\Actions\AdjustStockAction;
use App\Modules\Inventory\Actions\SetOpeningStockAction;
use App\Modules\Inventory\Enums\AdjustmentReason;
use App\Modules\Inventory\Http\Requests\AdjustStockRequest;
use App\Modules\Inventory\Http\Requests\OpeningStockRequest;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockMovement;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Stock of the current branch. Costs and values only for users who may see costs.
 */
final class InventoryController
{
    public function __construct(
        private readonly CurrentBranch $branch,
        private readonly CurrentTenant $tenant,
        private readonly VariantCatalog $catalog,
    ) {}

    /**
     * ?q= &category_id= &status=all|in|low|out &page=
     */
    public function index(Request $request): JsonResponse
    {
        $levels = $this->levels();
        $thresholds = $this->catalog->minStockThresholds();
        $inStock = array_keys(array_filter($levels, fn (array $l): bool => $l['qty'] > 0));

        [$onlyIds, $exceptIds] = match ($request->query('status')) {
            'in' => [$inStock, []],
            'out' => [null, $inStock],
            'low' => [$this->lowIds($levels, $thresholds), []],
            default => [null, []],
        };

        $page = $this->catalog->search(
            q: $request->filled('q') ? (string) $request->query('q') : null,
            categoryId: $request->filled('category_id') ? $request->integer('category_id') : null,
            onlyIds: $onlyIds,
            page: max(1, $request->integer('page', 1)),
            perPage: 30,
            exceptIds: $exceptIds,
        );

        $canCost = (bool) $request->user()?->can('products.view_cost');

        return response()->json([
            'data' => array_map(fn (VariantSummary $v): array => $this->row($v, $levels[$v->id] ?? null, $canCost), $page['items']),
            'meta' => ['current_page' => $page['page'], 'last_page' => $page['last_page'], 'per_page' => $page['per_page'], 'total' => $page['total']],
        ]);
    }

    public function summary(Request $request): JsonResponse
    {
        $levels = $this->levels();
        $total = $this->catalog->search(null, null, null, 1, 1)['total'];
        $positive = array_filter($levels, fn (array $l): bool => $l['qty'] > 0);

        return response()->json(['data' => [
            'variants' => $total,
            'in_stock' => count($positive),
            'out_of_stock' => max(0, $total - count($positive)),
            'low' => count($this->lowIds($levels, $this->catalog->minStockThresholds())),
            'units' => array_sum(array_column($positive, 'qty')),
            'value' => $request->user()?->can('products.view_cost')
                ? array_sum(array_map(fn (array $l): int => $l['qty'] * $l['avg_cost'], $positive))
                : null,
        ]]);
    }

    public function movements(Request $request, string $variant): JsonResponse
    {
        $summary = $this->catalog->find([$variant])[$variant]
            ?? abort(response()->json(['message' => 'الصنف مش موجود.', 'code' => 'variant_not_found'], 404));
        $canCost = (bool) $request->user()?->can('products.view_cost');
        $level = StockLevel::query()->where('branch_id', $this->branch->idOrFail())->where('variant_id', $variant)->first();

        $movements = StockMovement::query()
            ->where('branch_id', $this->branch->idOrFail())
            ->where('variant_id', $variant)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(200)
            ->get()
            ->map(fn (StockMovement $m): array => [
                'id' => $m->id,
                'type' => $m->type->value,
                'type_label' => $m->type->label(),
                'qty' => $m->qty,
                'balance_after' => $m->balance_after,
                'unit_cost' => $canCost ? $m->unit_cost : null,
                'reason' => $m->reason,
                'reason_label' => $m->reason ? AdjustmentReason::tryFrom($m->reason)?->label() : null,
                'note' => $m->note,
                'ref_type' => $m->ref_type,
                'ref_id' => $m->ref_id,
                'user_name' => $m->user_name,
                'created_at' => $m->created_at->toIso8601String(),
            ]);

        return response()->json(['data' => [
            'item' => $this->row($summary, $level ? ['qty' => $level->qty, 'avg_cost' => $level->avg_cost] : null, $canCost),
            'movements' => $movements,
        ]]);
    }

    public function opening(OpeningStockRequest $request, SetOpeningStockAction $action): JsonResponse
    {
        $units = $action->handle($this->tenant->idOrFail(), $this->branch->idOrFail(), $request->items());

        return response()->json(['data' => ['units' => $units]], 201);
    }

    public function adjust(AdjustStockRequest $request, AdjustStockAction $action): JsonResponse
    {
        $changes = $action->handle(
            $this->tenant->idOrFail(),
            $this->branch->idOrFail(),
            $request->enum('reason', AdjustmentReason::class) ?? AdjustmentReason::Count,
            $request->validated('note'),
            $request->items(),
        );

        return response()->json(['data' => ['changes' => $changes]]);
    }

    public function reasons(): JsonResponse
    {
        return response()->json(['data' => array_map(
            fn (AdjustmentReason $r): array => ['value' => $r->value, 'label' => $r->label()],
            AdjustmentReason::cases(),
        )]);
    }

    /**
     * @return array<string, array{qty: int, avg_cost: int}>
     */
    private function levels(): array
    {
        return StockLevel::query()
            ->where('branch_id', $this->branch->idOrFail())
            ->get(['variant_id', 'qty', 'avg_cost'])
            ->mapWithKeys(fn (StockLevel $l): array => [$l->variant_id => ['qty' => $l->qty, 'avg_cost' => $l->avg_cost]])
            ->all();
    }

    /**
     * @param  array<string, array{qty: int, avg_cost: int}>  $levels
     * @param  array<string, int>  $thresholds
     * @return list<string>
     */
    private function lowIds(array $levels, array $thresholds): array
    {
        return array_keys(array_filter($thresholds, fn (int $min, string $id): bool => ($levels[$id]['qty'] ?? 0) <= $min, ARRAY_FILTER_USE_BOTH));
    }

    /**
     * @param  array{qty: int, avg_cost: int}|null  $level
     * @return array<string, mixed>
     */
    private function row(VariantSummary $v, ?array $level, bool $canCost): array
    {
        $qty = $level['qty'] ?? 0;

        return [
            ...$v->toArray(),
            'qty' => $qty,
            'status' => $qty <= 0 ? 'out' : ($v->minStock > 0 && $qty <= $v->minStock ? 'low' : 'ok'),
            'avg_cost' => $canCost ? ($level['avg_cost'] ?? 0) : null,
            'value' => $canCost ? max(0, $qty) * ($level['avg_cost'] ?? 0) : null,
        ];
    }
}
