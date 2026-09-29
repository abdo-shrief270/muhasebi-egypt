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
            'items.*.serials' => ['nullable', 'array', 'max:5000'],
            'items.*.serials.*' => ['string', 'regex:/^[A-Za-z0-9 \-\/]{4,48}$/'],
        ];
    }

    public function attributes(): array
    {
        return ['items.*.qty' => 'الكمية', 'items.*.unit_cost' => 'سعر التكلفة', 'items.*.serials.*' => 'السيريال'];
    }

    /**
     * Without cost visibility the cost can't be entered; it is recorded as 0.
     *
     * @return list<array{variant_id: string, qty: int, unit_cost: int, serials: list<string>|null}>
     */
    public function items(): array
    {
        $canCost = (bool) $this->user()?->can('products.view_cost');

        return array_values(array_map(fn (array $i): array => [
            'variant_id' => (string) $i['variant_id'],
            'qty' => (int) $i['qty'],
            'unit_cost' => $canCost ? (int) ($i['unit_cost'] ?? 0) : 0,
            'serials' => isset($i['serials']) ? array_values(array_map('strval', $i['serials'])) : null,
        ], $this->validated('items')));
    }
}
