<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class PurchaseReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('suppliers.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.purchase_item_id' => ['required', 'integer', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.serials' => ['nullable', 'array'],
            'items.*.serials.*' => ['string', 'max:48'],
        ];
    }

    public function attributes(): array
    {
        return ['items.*.qty' => 'الكمية'];
    }

    /**
     * @return list<array{purchase_item_id: int, qty: int, serials: list<string>|null}>
     */
    public function lines(): array
    {
        return array_values(array_map(fn (array $l): array => [
            'purchase_item_id' => (int) $l['purchase_item_id'],
            'qty' => (int) $l['qty'],
            'serials' => isset($l['serials']) ? array_values(array_map('strval', $l['serials'])) : null,
        ], $this->validated('items')));
    }
}
