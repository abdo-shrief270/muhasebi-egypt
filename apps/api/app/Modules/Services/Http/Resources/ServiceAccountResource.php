<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Resources;

use App\Modules\Services\Models\ServiceAccount;
use App\Modules\Services\Support\Fees;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin ServiceAccount
 */
final class ServiceAccountResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $seesCost = (bool) ($request->user()?->can('services.settings') || $request->user()?->can('reports.profit'));

        return [
            'id' => $this->id,
            'branch_id' => $this->branch_id,
            'kind' => $this->kind->value,
            'kind_label' => $this->kind->label(),
            'provider' => $this->provider->value,
            'provider_label' => $this->providerLabel(),
            'name' => $this->name,
            'phone' => $this->phone,
            'balance' => $this->balance,
            // What the balance cost (airtime bought at a discount): shows the margin, so not for everyone.
            'cost_value' => $seesCost ? $this->cost_value : null,
            'daily_limit' => $this->daily_limit,
            'today_used' => (int) ($this->resource->getAttributes()['today_used'] ?? 0),
            'withdraw_fee_mode' => $this->withdraw_fee_mode->value,
            'is_active' => $this->is_active,
            'operations' => array_map(fn ($op) => $op->value, $this->kind->operations()),
            'fees' => Fees::rulesOf($this->resource),
        ];
    }
}
