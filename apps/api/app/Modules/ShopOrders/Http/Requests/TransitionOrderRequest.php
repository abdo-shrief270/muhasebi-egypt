<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Http\Requests;

use App\Modules\ShopOrders\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionOrderRequest extends FormRequest
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
            'status' => ['required', Rule::enum(OrderStatus::class)],
            'note' => ['nullable', 'string', 'max:190'],
            'prices' => ['nullable', 'array'],
            'prices.*' => ['integer', 'min:0'],
        ];
    }

    /**
     * @return array<int, int> item id => unit price (piasters)
     */
    public function prices(): array
    {
        $prices = [];
        foreach ((array) $this->validated('prices', []) as $itemId => $price) {
            $prices[(int) $itemId] = (int) $price;
        }

        return $prices;
    }
}
