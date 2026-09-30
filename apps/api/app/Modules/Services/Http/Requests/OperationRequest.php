<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Requests;

use App\Modules\Services\Enums\OperationType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;

final class OperationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('services.manage');
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('customer_phone');
        if (is_string($phone) && trim($phone) !== '') {
            try {
                $this->merge(['customer_phone' => (new PhoneNumber($phone, 'EG'))->formatE164()]);
            } catch (\Throwable) {
                // Leave as-is; the phone rule reports it.
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'account_id' => ['required', 'uuid'],
            'type' => ['required', Rule::in(array_map(fn (OperationType $t) => $t->value, OperationType::customer()))],
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            // Omitted = the account's suggested fee; a different one needs services.fees.
            'fee' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'phone:EG'],
            'reference' => ['nullable', 'string', 'max:60'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'المبلغ', 'fee' => 'العمولة', 'account_id' => 'الحساب', 'customer_phone' => 'رقم العميل'];
    }

    public function messages(): array
    {
        return ['customer_phone.phone' => 'رقم العميل مش صحيح.'];
    }
}
