<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Requests;

use App\Modules\Suppliers\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CreatePurchaseRequest extends FormRequest
{
    private const MAX_MONEY = 100_000_000_000;

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
            'supplier_id' => ['required', 'uuid'],
            'supplier_invoice_no' => ['nullable', 'string', 'max:60'],
            'invoice_date' => ['required', 'date', 'before_or_equal:today'],
            'discount' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MONEY],
            'paid' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MONEY],
            'payment_method' => ['nullable', Rule::enum(PaymentMethod::class)],
            // false: paid from outside the drawer (the owner's pocket, the safe).
            'from_drawer' => ['nullable', 'boolean'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:300'],
            'items.*.variant_id' => ['required', 'uuid', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:1000000'],
            'items.*.unit_cost' => ['required', 'integer', 'min:0', 'max:'.self::MAX_MONEY],
            'items.*.serials' => ['nullable', 'array', 'max:5000'],
            'items.*.serials.*' => ['string', 'regex:/^[A-Za-z0-9 \-\/]{4,48}$/'],
        ];
    }

    public function attributes(): array
    {
        return [
            'supplier_id' => 'المورد',
            'invoice_date' => 'تاريخ الفاتورة',
            'items' => 'الأصناف',
            'items.*.qty' => 'الكمية',
            'items.*.unit_cost' => 'سعر الشراء',
            'items.*.serials.*' => 'السيريال',
            'items.*.variant_id' => 'الصنف',
            'paid' => 'المدفوع',
            'payment_method' => 'طريقة الدفع',
        ];
    }

    public function messages(): array
    {
        return ['items.*.variant_id.distinct' => 'الصنف متكرر في الفاتورة. زوّد الكمية بدل ما تكرره.'];
    }

    /**
     * @return list<array{variant_id: string, qty: int, unit_cost: int, serials: list<string>|null}>
     */
    public function items(): array
    {
        return array_values(array_map(fn (array $i): array => [
            'variant_id' => (string) $i['variant_id'],
            'qty' => (int) $i['qty'],
            'unit_cost' => (int) $i['unit_cost'],
            'serials' => isset($i['serials']) ? array_values(array_map('strval', $i['serials'])) : null,
        ], $this->validated('items')));
    }
}
