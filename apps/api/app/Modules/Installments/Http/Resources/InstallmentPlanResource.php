<?php

declare(strict_types=1);

namespace App\Modules\Installments\Http\Resources;

use App\Modules\Installments\Models\InstallmentItem;
use App\Modules\Installments\Models\InstallmentPayment;
use App\Modules\Installments\Models\InstallmentPlan;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Carbon;

/** @mixin InstallmentPlan */
final class InstallmentPlanResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $today = Carbon::today();
        $open = $this->resource->relationLoaded('items')
            ? $this->items->filter(fn (InstallmentItem $i) => $i->remaining() > 0)
            : null;
        $next = $open?->first();
        $late = $open?->filter(fn (InstallmentItem $i) => $i->due_on->lt($today));

        return [
            'id' => $this->id,
            'reference' => $this->reference(),
            'branch_id' => $this->branch_id,
            'customer_id' => $this->customer_id,
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'sale_id' => $this->sale_id,
            'sale_reference' => $this->sale_reference,
            'principal' => $this->principal,
            'markup' => $this->markup,
            'markup_rate' => $this->markup_rate,
            'total' => $this->total,
            'paid' => $this->paid,
            'remaining' => $this->remaining(),
            'count' => $this->count,
            'interval_months' => $this->interval_months,
            'first_due_on' => $this->first_due_on->toDateString(),
            'guarantor_name' => $this->guarantor_name,
            'guarantor_phone' => $this->guarantor_phone,
            'notes' => $this->notes,
            'status' => $this->status->value,
            'status_label' => $this->status->label(),
            'created_by_name' => $this->created_by_name,
            'created_at' => $this->created_at->toIso8601String(),
            'completed_at' => $this->completed_at?->toIso8601String(),
            'cancelled_at' => $this->cancelled_at?->toIso8601String(),
            'cancelled_by_name' => $this->cancelled_by_name,
            'paid_count' => $this->whenLoaded('items', fn () => $this->items->filter(fn (InstallmentItem $i) => $i->remaining() === 0)->count()),
            'next_due' => $this->whenLoaded('items', fn () => $next ? self::item($next, $today) : null),
            'late_amount' => $this->whenLoaded('items', fn () => (int) $late->sum(fn (InstallmentItem $i) => $i->remaining())),
            'late_count' => $this->whenLoaded('items', fn () => $late->count()),
            'items' => $this->when($this->resource->relationLoaded('items') && ! $request->routeIs('installments.index'), fn () => $this->items->map(fn (InstallmentItem $i) => self::item($i, $today))->values()),
            'payments' => $this->whenLoaded('payments', fn () => $this->payments->map(fn (InstallmentPayment $p) => [
                'id' => $p->id,
                'amount' => $p->amount,
                'method' => $p->method,
                'source' => $p->source,
                'user_name' => $p->user_name,
                'created_at' => $p->created_at->toIso8601String(),
            ])->values()),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function item(InstallmentItem $item, Carbon $today): array
    {
        return [
            'id' => $item->id,
            'seq' => $item->seq,
            'due_on' => $item->due_on->toDateString(),
            'amount' => $item->amount,
            'paid' => $item->paid,
            'remaining' => $item->remaining(),
            'paid_at' => $item->paid_at?->toIso8601String(),
            'days_late' => $item->daysLate($today),
        ];
    }
}
