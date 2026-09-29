<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests;

use App\Modules\Sales\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaleReturnRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('sales.refund');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'refund_method' => ['required', Rule::enum(PaymentMethod::class)],
            'reason' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.sale_item_id' => ['required', 'integer', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1'],
            'items.*.restock' => ['required', 'boolean'],
            'items.*.serials' => ['nullable', 'array'],
            'items.*.serials.*' => ['string', 'max:48'],
        ];
    }

    public function attributes(): array
    {
        return ['items.*.qty' => 'الكمية', 'refund_method' => 'طريقة رد الفلوس'];
    }

    /**
     * @return list<array{sale_item_id: int, qty: int, restock: bool, serials: list<string>|null}>
     */
    public function lines(): array
    {
        return array_values(array_map(fn (array $l): array => [
            'sale_item_id' => (int) $l['sale_item_id'],
            'qty' => (int) $l['qty'],
            'restock' => (bool) $l['restock'],
            'serials' => isset($l['serials']) ? array_values(array_map('strval', $l['serials'])) : null,
        ], $this->validated('items')));
    }
}
