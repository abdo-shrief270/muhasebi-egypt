<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Http\Requests;

use App\Modules\ShopOrders\Enums\OrderType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class PlaceOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'seller_tenant_id' => ['required', 'uuid'],
            'type' => ['required', Rule::enum(OrderType::class)],
            'needed_by' => ['nullable', 'date', 'after_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.description' => ['required', 'string', 'max:190'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.device_model' => ['nullable', 'string', 'max:120'],
            'items.*.imei' => ['nullable', 'digits_between:14,16'],
            'items.*.note' => ['nullable', 'string', 'max:190'],
        ];
    }

    public function attributes(): array
    {
        return [
            'items.*.description' => 'وصف الصنف',
            'items.*.quantity' => 'الكمية',
            'items.*.imei' => 'IMEI',
        ];
    }

    /**
     * @return list<array{description: string, quantity: int, device_model: string|null, imei: string|null, note: string|null}>
     */
    public function items(): array
    {
        return array_values(array_map(fn (array $item): array => [
            'description' => (string) $item['description'],
            'quantity' => (int) $item['quantity'],
            'device_model' => $item['device_model'] ?? null,
            'imei' => $item['imei'] ?? null,
            'note' => $item['note'] ?? null,
        ], $this->validated('items')));
    }
}
