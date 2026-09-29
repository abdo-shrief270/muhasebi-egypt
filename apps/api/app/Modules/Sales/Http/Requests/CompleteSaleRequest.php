<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests;

use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\PriceLevel;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CompleteSaleRequest extends FormRequest
{
    private const MAX_MONEY = 100_000_000_000;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('sales.sell');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid'],
            'price_level' => ['nullable', Rule::enum(PriceLevel::class)],
            'discount' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MONEY],
            'customer_id' => ['nullable', 'uuid'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.variant_id' => ['required', 'uuid', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'items.*.discount' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MONEY],
            'payments' => ['required', 'array', 'min:1', 'max:5'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_MONEY],
            'payments.*.reference' => ['nullable', 'string', 'max:60'],
        ];
    }

    public function attributes(): array
    {
        return [
            'items' => 'الأصناف',
            'items.*.qty' => 'الكمية',
            'items.*.discount' => 'خصم الصنف',
            'payments' => 'الدفع',
            'payments.*.amount' => 'المبلغ',
            'payments.*.method' => 'طريقة الدفع',
        ];
    }

    /**
     * @return list<array{variant_id: string, qty: int, discount: int}>
     */
    public function items(): array
    {
        return array_values(array_map(fn (array $i): array => [
            'variant_id' => (string) $i['variant_id'],
            'qty' => (int) $i['qty'],
            'discount' => (int) ($i['discount'] ?? 0),
        ], $this->validated('items')));
    }

    /**
     * @return list<array{method: PaymentMethod, amount: int, reference: string|null}>
     */
    public function payments(): array
    {
        return array_values(array_map(fn (array $p): array => [
            'method' => PaymentMethod::from((string) $p['method']),
            'amount' => (int) $p['amount'],
            'reference' => $p['reference'] ?? null,
        ], $this->validated('payments')));
    }
}
