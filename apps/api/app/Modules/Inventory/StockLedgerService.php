<?php

declare(strict_types=1);

namespace App\Modules\Inventory;

use App\Modules\Inventory\Contracts\StockIssue;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Inventory\Contracts\StockPortion;
use App\Modules\Inventory\Contracts\StockReference;
use App\Modules\Inventory\Models\StockLevel;
use App\Modules\Inventory\Models\StockLot;
use App\Modules\Inventory\Models\StockMovement;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Row locks on the (branch, variant) balance serialise concurrent movements of the same item,
 * so two sales of the last unit can't both see it in stock.
 */
final class StockLedgerService implements StockLedger
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly Auth $auth,
    ) {}

    public function receive(string $branchId, string $variantId, int $qty, int $unitCost, StockReference $reference): string
    {
        if ($qty <= 0 || $unitCost < 0) {
            throw new InvalidArgumentException('Receive a positive quantity at a non-negative cost.');
        }

        return DB::transaction(function () use ($branchId, $variantId, $qty, $unitCost, $reference): string {
            $level = $this->lockLevel($branchId, $variantId);

            // Units sold while the balance was negative were never taken from a lot:
            // this receipt covers them first, so lots always add up to the positive balance.
            $covering = min($qty, max(0, -$level->qty));

            $lot = StockLot::create([
                'tenant_id' => $level->tenant_id,
                'branch_id' => $branchId,
                'variant_id' => $variantId,
                'source_type' => $reference->type->value,
                'source_id' => $reference->refId,
                'unit_cost' => $unitCost,
                'qty_in' => $qty,
                'qty_remaining' => $qty - $covering,
                'received_at' => now(),
            ]);

            $level->avg_cost = $level->qty > 0
                ? intdiv($level->qty * $level->avg_cost + $qty * $unitCost + intdiv($level->qty + $qty, 2), $level->qty + $qty)
                : $unitCost;
            $level->qty += $qty;
            $level->save();

            $this->record($level, $lot->id, $qty, $unitCost, $reference);

            return $lot->id;
        });
    }

    public function issue(string $branchId, string $variantId, int $qty, StockReference $reference, ?string $fromLotId = null): StockIssue
    {
        if ($qty <= 0) {
            throw new InvalidArgumentException('Issue a positive quantity.');
        }

        return DB::transaction(function () use ($branchId, $variantId, $qty, $reference, $fromLotId): StockIssue {
            $level = $this->lockLevel($branchId, $variantId);

            $lots = StockLot::query()
                ->where('branch_id', $branchId)
                ->where('variant_id', $variantId)
                ->where('qty_remaining', '>', 0)
                ->when($fromLotId !== null, fn ($q) => $q->orderByRaw('id = ? desc', [$fromLotId]))
                ->orderBy('seq')
                ->lockForUpdate()
                ->get();

            $portions = [];
            $left = $qty;

            foreach ($lots as $lot) {
                $take = min($left, $lot->qty_remaining);
                $lot->decrement('qty_remaining', $take);
                $portions[] = new StockPortion($lot->id, $take, $lot->unit_cost);
                $left -= $take;
                if ($left === 0) {
                    break;
                }
            }

            if ($left > 0) {
                $portions[] = new StockPortion(null, $left, $level->avg_cost);
            }

            foreach ($portions as $portion) {
                $level->qty -= $portion->qty;
                $this->record($level, $portion->lotId, -$portion->qty, $portion->unitCost, $reference);
            }
            $level->save();

            return new StockIssue($portions, $level->qty);
        });
    }

    public function quantity(string $branchId, string $variantId): int
    {
        return (int) StockLevel::query()->where('branch_id', $branchId)->where('variant_id', $variantId)->value('qty');
    }

    public function quantities(string $branchId, array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        $found = StockLevel::query()
            ->where('branch_id', $branchId)
            ->whereIn('variant_id', $variantIds)
            ->pluck('qty', 'variant_id')
            ->map(fn ($qty) => (int) $qty)
            ->all();

        return array_replace(array_fill_keys($variantIds, 0), $found);
    }

    public function averageCosts(string $branchId, array $variantIds): array
    {
        if ($variantIds === []) {
            return [];
        }

        return StockLevel::query()
            ->where('branch_id', $branchId)
            ->whereIn('variant_id', $variantIds)
            ->pluck('avg_cost', 'variant_id')
            ->map(fn ($cost) => (int) $cost)
            ->all();
    }

    public function variantsWithHistory(array $variantIds, ?string $branchId = null): array
    {
        if ($variantIds === []) {
            return [];
        }

        return StockMovement::query()
            ->whereIn('variant_id', $variantIds)
            ->when($branchId !== null, fn ($q) => $q->where('branch_id', $branchId))
            ->distinct()
            ->pluck('variant_id')
            ->all();
    }

    private function lockLevel(string $branchId, string $variantId): StockLevel
    {
        $tenantId = $this->tenant->idOrFail();

        // Create the row if missing without racing another request doing the same.
        StockLevel::query()->insertOrIgnore([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'variant_id' => $variantId,
            'qty' => 0,
            'avg_cost' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return StockLevel::query()->where('branch_id', $branchId)->where('variant_id', $variantId)->lockForUpdate()->firstOrFail();
    }

    private function record(StockLevel $level, ?string $lotId, int $qty, int $unitCost, StockReference $reference): void
    {
        $user = $this->auth->guard('sanctum')->user();

        StockMovement::create([
            'tenant_id' => $level->tenant_id,
            'branch_id' => $level->branch_id,
            'variant_id' => $level->variant_id,
            'lot_id' => $lotId,
            'type' => $reference->type,
            'qty' => $qty,
            'unit_cost' => $unitCost,
            'balance_after' => $level->qty,
            'ref_type' => $reference->refType,
            'ref_id' => $reference->refId,
            'reason' => $reference->reason,
            'note' => $reference->note,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name'),
            'created_at' => now(),
        ]);
    }
}
