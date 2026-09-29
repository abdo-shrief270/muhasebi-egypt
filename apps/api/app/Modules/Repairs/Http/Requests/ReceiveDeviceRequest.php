<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Requests;

use App\Modules\Repairs\Support\IntakeOptions;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ReceiveDeviceRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('repairs.create');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenant = fn (Builder $q) => $q->where('tenant_id', app(CurrentTenant::class)->idOrFail());

        return [
            // An existing customer, or a name + phone (found by phone, or added).
            'customer_id' => ['nullable', 'uuid'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:120'],
            'customer_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:20'],
            // A typed-in customer agreed to having their data kept (Personal Data Protection Law 151/2020).
            'consent' => ['nullable', 'boolean'],
            'device_model_id' => ['nullable', 'integer', Rule::exists('device_models', 'id')->where($tenant)],
            'device_name' => ['required', 'string', 'max:160'],
            'imei' => ['nullable', 'string', 'max:40'],
            'color' => ['nullable', 'string', 'max:40'],
            'unlock_type' => ['nullable', Rule::in(IntakeOptions::UNLOCK_TYPES)],
            'unlock_code' => ['nullable', 'string', 'max:60'],
            'accessories' => ['nullable', 'array'],
            'accessories.*' => ['string', 'max:40'],
            'condition' => ['nullable', 'array'],
            'condition.*' => ['string', Rule::in(array_keys(IntakeOptions::CONDITION))],
            'checks' => ['nullable', 'array:'.implode(',', array_keys(IntakeOptions::CHECKS))],
            'checks.*' => [Rule::in(IntakeOptions::CHECK_VALUES)],
            'fault_ids' => ['nullable', 'array', 'max:20'],
            'fault_ids.*' => ['integer', 'distinct'],
            'reported_note' => ['nullable', 'string', 'max:1000'],
            'expected_at' => ['nullable', 'date'],
            'technician_id' => ['nullable', 'uuid'],
            'estimate' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'deposits' => ['nullable', 'array', 'max:4'],
            'deposits.*.method' => ['required', Rule::in(['cash', 'card', 'wallet', 'instapay'])],
            'deposits.*.amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
        ];
    }

    /**
     * @return list<callable>
     */
    public function after(): array
    {
        return [function ($validator): void {
            if (($this->input('fault_ids') ?? []) === [] && trim((string) $this->input('reported_note')) === '') {
                $validator->errors()->add('fault_ids', 'اختار العطل أو اكتب اللي العميل قاله.');
            }
        }];
    }

    public function attributes(): array
    {
        return ['device_name' => 'الجهاز', 'customer_phone' => 'موبايل العميل', 'customer_name' => 'اسم العميل', 'expected_at' => 'ميعاد التسليم'];
    }

    /**
     * @return list<array{method: string, amount: int}>
     */
    public function deposits(): array
    {
        return array_values(array_map(fn (array $d) => ['method' => (string) $d['method'], 'amount' => (int) $d['amount']], $this->validated('deposits') ?? []));
    }
}
