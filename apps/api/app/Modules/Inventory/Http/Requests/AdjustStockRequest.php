<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use App\Modules\Inventory\Enums\AdjustmentReason;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AdjustStockRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('inventory.adjust');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'reason' => ['required', Rule::enum(AdjustmentReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:1000'],
            'items.*.variant_id' => ['required', 'uuid', 'distinct'],
            'items.*.counted' => ['nullable', 'required_without:items.*.delta', 'prohibits:items.*.delta', 'integer', 'min:0', 'max:1000000'],
            'items.*.delta' => ['nullable', 'integer', 'min:-1000000', 'max:1000000', 'not_in:0'],
            'items.*.unit_cost' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
        ];
    }

    public function attributes(): array
    {
        return [
            'reason' => 'السبب',
            'items.*.counted' => 'الكمية الفعلية',
            'items.*.delta' => 'الفرق',
            'items.*.unit_cost' => 'سعر التكلفة',
        ];
    }

    /**
     * @return list<array{variant_id: string, counted: int|null, delta: int|null, unit_cost: int|null}>
     */
    public function items(): array
    {
        $canCost = (bool) $this->user()?->can('products.view_cost');

        return array_values(array_map(fn (array $i): array => [
            'variant_id' => (string) $i['variant_id'],
            'counted' => isset($i['counted']) ? (int) $i['counted'] : null,
            'delta' => isset($i['delta']) ? (int) $i['delta'] : null,
            'unit_cost' => $canCost && isset($i['unit_cost']) ? (int) $i['unit_cost'] : null,
        ], $this->validated('items')));
    }
}
