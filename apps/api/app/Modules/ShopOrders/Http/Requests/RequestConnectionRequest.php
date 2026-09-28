<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RequestConnectionRequest extends FormRequest
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
        return ['code' => ['required', 'string', 'max:12']];
    }
}
