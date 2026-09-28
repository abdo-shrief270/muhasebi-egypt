<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Http\Requests;

use App\Modules\ShopOrders\Enums\OrderStatus;
use App\Modules\ShopOrders\Enums\Party;
use App\Modules\ShopOrders\Models\ShopOrder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class TransitionOrderRequest extends FormRequest
{
    /** Buyers need "place", sellers need "fulfil". */
    public function authorize(): bool
    {
        $order = ShopOrder::query()->find($this->route('order'));
        $tenantId = $this->user()?->getAttribute('tenant_id');

        if ($order === null || $tenantId === null) {
            return true; // let the controller answer 404
        }

        return (bool) $this->user()?->can(
            $order->partyOf($tenantId) === Party::Buyer ? 'shop_orders.place' : 'shop_orders.fulfil',
        );
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
