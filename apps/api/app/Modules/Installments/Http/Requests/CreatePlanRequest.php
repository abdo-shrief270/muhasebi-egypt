<?php

declare(strict_types=1);

namespace App\Modules\Installments\Http\Requests;

use App\Support\Time\ShopDay;
use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;

final class CreatePlanRequest extends FormRequest
{
    private const MAX_MONEY = 100_000_000_000;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('installments.manage');
    }

    protected function prepareForValidation(): void
    {
        foreach (['sale_id', 'guarantor_name', 'guarantor_phone', 'notes', 'markup_rate'] as $field) {
            if (is_string($this->input($field)) && trim($this->input($field)) === '') {
                $this->merge([$field => null]);
            }
        }
        $phone = $this->input('guarantor_phone');
        if (is_string($phone)) {
            try {
                $this->merge(['guarantor_phone' => (new PhoneNumber($phone, 'EG'))->formatE164()]);
            } catch (\Throwable) {
                // The phone rule reports it.
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'customer_id' => ['required', 'uuid'],
            'sale_id' => ['nullable', 'uuid'],
            'principal' => ['required', 'integer', 'min:100', 'max:'.self::MAX_MONEY],
            'markup' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MONEY],
            'markup_rate' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'count' => ['required', 'integer', 'min:1', 'max:60'],
            'interval_months' => ['nullable', 'integer', 'in:1,2,3'],
            'first_due_on' => ['required', 'date_format:Y-m-d', 'after_or_equal:'.ShopDay::today()->toDateString(), 'before:'.ShopDay::today()->addYear()->toDateString()],
            'guarantor_name' => ['nullable', 'string', 'max:120'],
            'guarantor_phone' => ['nullable', 'string', 'phone:EG'],
            'notes' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'first_due_on.after_or_equal' => 'ميعاد أول قسط لازم يبقى النهارده أو بعده.',
            'guarantor_phone.phone' => 'رقم موبايل الضامن مش صحيح.',
            'principal.min' => 'المبلغ المقسّط لازم يبقى جنيه على الأقل.',
        ];
    }
}
