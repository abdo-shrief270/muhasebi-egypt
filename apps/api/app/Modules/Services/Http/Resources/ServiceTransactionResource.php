<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Resources;

use App\Modules\Services\Models\ServiceTransaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceTransaction
 */
final class ServiceTransactionResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $user = $request->user();
        // The fee is on the customer's slip; the profit (the airtime margin) is not for everyone.
        $seesProfit = (bool) ($user?->can('reports.profit') || $user?->can('services.settings'));
        $account = $this->relationLoaded('account') ? $this->account : null;

        return [
            'id' => $this->id,
            'number' => $this->number,
            'account_id' => $this->account_id,
            'account_name' => $account?->name,
            'account_kind' => $account?->kind->value,
            'provider_label' => $account?->providerLabel(),
            'type' => $this->type->value,
            'type_label' => $this->type->label(),
            'amount' => $this->amount,
            'fee' => $this->fee,
            'suggested_fee' => $this->suggested_fee,
            'balance_change' => $this->balance_change,
            'balance_after' => $this->balance_after,
            'cash' => $this->cash,
            'profit' => $seesProfit ? $this->profit : null,
            'fee_mode' => $this->fee_mode?->value,
            'source' => $this->source?->value,
            'source_label' => $this->source?->label(),
            'customer_name' => $this->customer_name,
            'customer_phone' => $this->customer_phone,
            'reference' => $this->reference,
            'note' => $this->note,
            'reverses_id' => $this->reverses_id,
            'reversed_number' => $this->reverses_id !== null && $this->relationLoaded('reversed') ? $this->reversed?->number : null,
            'reversed' => $this->reverses_id === null && (bool) ($this->resource->getAttributes()['reversal_exists'] ?? $this->reversal()->exists()),
            'user_id' => $this->user_id,
            'user_name' => $this->user_name,
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
