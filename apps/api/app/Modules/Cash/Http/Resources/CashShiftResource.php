<?php

declare(strict_types=1);

namespace App\Modules\Cash\Http\Resources;

use App\Modules\Cash\Models\CashShift;
use App\Modules\Cash\Support\ShiftTotals;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CashShift
 */
final class CashShiftResource extends JsonResource
{
    private bool $withDetail = false;

    /** Adds the totals by movement type and the movements themselves. */
    public function withDetail(bool $detail = true): self
    {
        $this->withDetail = $detail;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $totals = $this->isOpen() || $this->withDetail ? ShiftTotals::for($this->resource) : null;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'reference' => $this->reference(),
            'branch_id' => $this->branch_id,
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'opening_cash' => $this->opening_cash,
            'opened_at' => $this->opened_at->toIso8601String(),
            'closed_at' => $this->closed_at?->toIso8601String(),
            'closed_by_name' => $this->closed_by_name,
            'is_open' => $this->isOpen(),
            // Live while open; frozen at close.
            'expected' => $this->expected ?? $totals['expected'] ?? null,
            'counted' => $this->counted,
            'cash_difference' => $this->cash_difference,
            'note' => $this->note,
            'by_type' => $this->when($totals !== null, fn () => $totals['by_type'] ?? []),
            'movements' => $this->when($this->withDetail, fn () => CashMovementResource::collection(
                $this->movements()->orderByDesc('seq')->limit(500)->get(),
            )),
        ];
    }
}
