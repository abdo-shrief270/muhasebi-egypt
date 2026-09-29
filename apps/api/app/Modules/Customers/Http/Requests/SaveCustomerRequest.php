<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Requests;

use App\Modules\Customers\Models\Customer;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;

final class SaveCustomerRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user?->can('customers.manage')) {
            return false;
        }

        // Money on the account (a limit, an opening balance) is for whoever handles credit.
        return ! ($this->has('credit_limit') || $this->filled('opening_balance')) || $user->can('customers.credit');
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');
        if (is_string($phone) && trim($phone) !== '') {
            try {
                $this->merge(['phone' => (new PhoneNumber($phone, 'EG'))->formatE164()]);
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
        $customer = $this->route('customer');
        $creating = ! $customer instanceof Customer;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'phone:EG', Rule::unique('customers', 'phone')
                ->where(fn (Builder $q) => $q->where('tenant_id', app(CurrentTenant::class)->idOrFail()))
                ->ignore($creating ? null : $customer->id)],
            'notes' => ['nullable', 'string', 'max:1000'],
            'credit_limit' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'is_active' => ['sometimes', 'boolean'],
            // The customer agreed to having their data kept (Personal Data Protection Law 151/2020).
            'consent' => ['sometimes', 'nullable', 'boolean'],
            // Only when adding: what the customer already owed (negative = the shop owes them).
            'opening_balance' => [$creating ? 'nullable' : 'prohibited', 'integer', 'min:-100000000000', 'max:100000000000'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'اسم العميل', 'phone' => 'الموبايل', 'credit_limit' => 'حد الآجل', 'opening_balance' => 'الرصيد الافتتاحي'];
    }

    public function messages(): array
    {
        return ['phone.unique' => 'فيه عميل بالموبايل ده بالفعل.', 'phone.phone' => 'رقم الموبايل مش صحيح.'];
    }

    /** Whether the customer agreed to having their data kept; null when not asked on this form. */
    public function consent(): ?bool
    {
        $consent = $this->validated('consent');

        return $consent === null ? null : (bool) $consent;
    }

    /**
     * @return array{name?: string, phone?: string|null, notes?: string|null, credit_limit?: int|null, is_active?: bool}
     */
    public function customerData(): array
    {
        /** @var array{name?: string, phone?: string|null, notes?: string|null, credit_limit?: int|null, is_active?: bool} */
        return $this->safe()->only(['name', 'phone', 'notes', 'credit_limit', 'is_active']);
    }
}
