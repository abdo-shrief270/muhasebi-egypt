<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OpeningStockRequest extends FormRequest
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
            'items' => ['required', 'array', 'min:1', 'max:500'],
            'items.*.variant_id' => ['required', 'uuid', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
        ];
    }

    public function attributes(): array
    {
        return ['items.*.qty' => 'الكمية', 'items.*.unit_cost' => 'سعر التكلفة'];
    }

    /**
     * Without cost visibility the cost can't be entered; it is recorded as 0.
     *
     * @return list<array{variant_id: string, qty: int, unit_cost: int}>
     */
    public function items(): array
    {
        $canCost = (bool) $this->user()?->can('products.view_cost');

        return array_values(array_map(fn (array $i): array => [
            'variant_id' => (string) $i['variant_id'],
            'qty' => (int) $i['qty'],
            'unit_cost' => $canCost ? (int) ($i['unit_cost'] ?? 0) : 0,
        ], $this->validated('items')));
    }
}
