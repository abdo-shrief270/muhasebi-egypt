<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests;

use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\SupplierReturns\Contracts\ReturnReason;
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
            // Why a damaged unit is bad (it goes to the supplier returns bin when that module is on).
            'items.*.defect_reason' => ['nullable', Rule::enum(ReturnReason::class)],
            'items.*.serials' => ['nullable', 'array'],
            'items.*.serials.*' => ['string', 'max:48'],
        ];
    }

    public function attributes(): array
    {
        return ['items.*.qty' => 'الكمية', 'refund_method' => 'طريقة رد الفلوس'];
    }

    /**
     * @return list<array{sale_item_id: int, qty: int, restock: bool, serials: list<string>|null, defect_reason: string|null}>
     */
    public function lines(): array
    {
        return array_values(array_map(fn (array $l): array => [
            'sale_item_id' => (int) $l['sale_item_id'],
            'qty' => (int) $l['qty'],
            'restock' => (bool) $l['restock'],
            'serials' => isset($l['serials']) ? array_values(array_map('strval', $l['serials'])) : null,
            'defect_reason' => $l['defect_reason'] ?? null,
        ], $this->validated('items')));
    }
}
