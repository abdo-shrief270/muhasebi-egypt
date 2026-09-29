<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Requests;

use App\Modules\Suppliers\Enums\PaymentMethod;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SupplierPaymentRequest extends FormRequest
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
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'from_drawer' => ['nullable', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'المبلغ', 'payment_method' => 'طريقة الدفع'];
    }
}
