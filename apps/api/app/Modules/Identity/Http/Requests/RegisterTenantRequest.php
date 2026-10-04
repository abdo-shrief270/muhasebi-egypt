<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Data\RegisterTenantData;
use App\Modules\Identity\Enums\ShopType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Propaganistas\LaravelPhone\PhoneNumber;

final class RegisterTenantRequest extends FormRequest
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
            'shop_name' => ['required', 'string', 'max:120'],
            // One or more types; shop_type (a single one) is still accepted from older clients.
            'shop_types' => ['required_without:shop_type', 'array', 'min:1', 'max:5'],
            'shop_types.*' => ['distinct', Rule::in(array_map(fn (ShopType $t) => $t->value, ShopType::selectable()))],
            'shop_type' => ['nullable', Rule::enum(ShopType::class)],
            'owner_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'phone:EG', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'branch_name' => ['nullable', 'string', 'max:120'],
            'device_name' => ['nullable', 'string', 'max:120'],
            // Another shop's invite code (its shop code): a welcome discount for this one, points for that one.
            'referral_code' => ['nullable', 'string', 'max:12', Rule::exists('tenants', 'code')],
            // The campaign the owner came from (utm_* carried from the website / an ad link).
            'acquisition' => ['nullable', 'array:source,medium,campaign,content,term'],
            'acquisition.*' => ['nullable', 'string', 'max:80'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('referral_code'))) {
            $this->merge(['referral_code' => strtoupper(trim($this->input('referral_code'))) ?: null]);
        }
        $phone = $this->input('phone');

        if (is_string($phone) && $phone !== '') {
            try {
                $this->merge(['phone' => (new PhoneNumber($phone, 'EG'))->formatE164()]);
            } catch (\Throwable) {
                // Leave as-is; the phone rule reports it.
            }
        }
    }

    public function attributes(): array
    {
        return ['shop_types' => 'نوع المحل', 'shop_types.*' => 'نوع المحل', 'referral_code' => 'كود الدعوة'];
    }

    public function messages(): array
    {
        return ['shop_types.required_without' => 'اختار نوع المحل (نوع واحد على الأقل).'];
    }

    /** @return list<ShopType> */
    private function shopTypes(): array
    {
        if ($this->filled('shop_types')) {
            return array_values(array_map(fn ($v) => ShopType::from((string) $v), (array) $this->input('shop_types')));
        }

        return ($this->enum('shop_type', ShopType::class) ?? ShopType::Accessories)->parts();
    }

    public function toData(): RegisterTenantData
    {
        return new RegisterTenantData(
            shopName: $this->string('shop_name')->toString(),
            shopTypes: $this->shopTypes(),
            ownerName: $this->string('owner_name')->toString(),
            phone: $this->string('phone')->toString(),
            email: $this->filled('email') ? $this->string('email')->toString() : null,
            password: $this->string('password')->toString(),
            branchName: $this->filled('branch_name') ? $this->string('branch_name')->toString() : 'الفرع الرئيسي',
            referralCode: $this->filled('referral_code') ? $this->string('referral_code')->toString() : null,
            acquisition: array_filter(array_map(fn ($v) => is_string($v) ? trim($v) : null, (array) $this->input('acquisition', [])), fn ($v) => $v !== null && $v !== ''),
        );
    }
}
