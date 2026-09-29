<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\PriceHistory;
use App\Modules\Catalog\Support\PriceRule;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Changes one price of many variants at once. preview() shows what would change; apply() does it,
 * recomputing from the locked rows, and writes the price history and the audit log.
 */
final class BulkPriceAction
{
    public const MAX_VARIANTS = 3000;

    public function __construct(
        private readonly StockLedger $stock,
        private readonly PriceHistory $history,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array{category_id?: int|null, brand_id?: int|null, device_model_id?: int|null, q?: string|null, variant_ids?: list<string>|null, include_inactive?: bool}  $filters
     * @return array{rows: list<array<string, mixed>>, summary: array<string, int>}
     */
    public function preview(string $branchId, array $filters, PriceRule $rule, bool $withCost): array
    {
        $variants = $this->query($filters)->get();

        return $this->plan($branchId, $variants, $rule, $withCost);
    }

    /**
     * @param  list<string>  $excluded  variants left unchanged although they match
     * @return array{changed: int, batch_id: string}
     */
    public function apply(string $tenantId, string $branchId, array $filters, PriceRule $rule, array $excluded): array
    {
        return DB::transaction(function () use ($tenantId, $branchId, $filters, $rule, $excluded): array {
            $variants = $this->query($filters)
                ->when($excluded !== [], fn (Builder $q) => $q->whereKeyNot($excluded))
                ->lockForUpdate()
                ->get();
            $plan = $this->plan($branchId, $variants, $rule, false);
            $batchId = (string) Str::uuid7();
            $byId = $variants->keyBy('id');
            $changed = 0;

            foreach ($plan['rows'] as $row) {
                if ($row['skip'] !== null) {
                    continue;
                }
                /** @var ProductVariant $variant */
                $variant = $byId->get($row['variant_id']);
                $variant->setAttribute($rule->field, $row['new']);
                $variant->save();
                $this->history->record($variant, $rule->field, $row['old'], $row['new'], 'bulk', $batchId);
                $changed++;
            }

            if ($changed === 0) {
                throw new DomainRuleException('مفيش أسعار هتتغير بالقاعدة دي.', 'nothing_to_change');
            }

            $this->audit->record(
                'products.prices_bulk_updated',
                "عدّل أسعار {$changed} صنف — {$rule->describe()}",
                null,
                ['batch_id' => $batchId, 'field' => $rule->field, 'base' => $rule->base, 'change' => $rule->change, 'value' => $rule->value, 'step' => $rule->step, 'rounding' => $rule->rounding, 'count' => $changed],
                $tenantId,
            );

            return ['changed' => $changed, 'batch_id' => $batchId];
        });
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return Builder<ProductVariant>
     */
    private function query(array $filters): Builder
    {
        $productFilter = function (Builder $p) use ($filters): void {
            $p->when($filters['category_id'] ?? null, fn (Builder $q, $id) => $q->where('category_id', $id))
                ->when($filters['brand_id'] ?? null, fn (Builder $q, $id) => $q->where('brand_id', $id))
                ->when($filters['device_model_id'] ?? null, fn (Builder $q, $id) => $q->whereHas('deviceModels', fn (Builder $m) => $m->whereKey($id)))
                ->when(filled($filters['q'] ?? null), fn (Builder $q) => $q->search((string) $filters['q']))
                ->when(! ($filters['include_inactive'] ?? false), fn (Builder $q) => $q->where('is_active', true));
        };

        $query = ProductVariant::query()
            ->with('product:id,name')
            ->whereHas('product', $productFilter)
            ->when(! ($filters['include_inactive'] ?? false), fn (Builder $q) => $q->where('is_active', true))
            ->when($filters['variant_ids'] ?? null, fn (Builder $q, array $ids) => $q->whereKey($ids));

        if ((clone $query)->count() > self::MAX_VARIANTS) {
            throw new DomainRuleException('الأصناف كتير أوي في مرة واحدة ('.self::MAX_VARIANTS.' بالكتير). ضيّق الاختيار بالتصنيف أو الماركة.', 'too_many_variants', 422);
        }

        return $query;
    }

    /**
     * @param  Collection<int, ProductVariant>  $variants
     * @return array{rows: list<array<string, mixed>>, summary: array<string, int>}
     */
    private function plan(string $branchId, Collection $variants, PriceRule $rule, bool $withCost): array
    {
        $ids = $variants->modelKeys();
        $needCost = $withCost || $rule->base === 'cost';
        $costs = $needCost && $ids !== [] ? $this->stock->averageCosts($branchId, $ids) : [];

        $rows = $variants
            ->sortBy(fn (ProductVariant $v) => [$v->product->name, $v->sort])
            ->map(function (ProductVariant $v) use ($rule, $costs, $withCost): array {
                $cost = $costs[$v->id] ?? null;
                $old = $v->getAttribute($rule->field);
                $base = $rule->base === 'cost' ? $cost : $v->getAttribute($rule->base);
                $new = $rule->apply($base);

                $skip = match (true) {
                    $new === null && $rule->base === 'cost' => 'no_cost',
                    $new === null => 'no_base',
                    $new <= 0 => 'not_positive',
                    $new === $old => 'unchanged',
                    default => null,
                };

                return [
                    'variant_id' => $v->id,
                    'product_id' => $v->product_id,
                    'name' => $v->name ? "{$v->product->name} — {$v->name}" : $v->product->name,
                    'barcode' => $v->barcode,
                    'old' => $old,
                    'new' => $skip === null ? $new : null,
                    'skip' => $skip,
                    'cost' => $withCost ? $cost : null,
                    'below_cost' => $withCost && $skip === null && $cost !== null && $new < $cost,
                ];
            })
            ->values()
            ->all();

        $changing = array_filter($rows, fn (array $r) => $r['skip'] === null);

        return [
            'rows' => $rows,
            'summary' => [
                'matched' => count($rows),
                'changing' => count($changing),
                'skipped' => count($rows) - count($changing),
                'below_cost' => count(array_filter($rows, fn (array $r) => $r['below_cost'])),
                'raised' => count(array_filter($changing, fn (array $r) => $r['new'] > ($r['old'] ?? 0))),
                'lowered' => count(array_filter($changing, fn (array $r) => $r['old'] !== null && $r['new'] < $r['old'])),
            ],
        ];
    }
}
