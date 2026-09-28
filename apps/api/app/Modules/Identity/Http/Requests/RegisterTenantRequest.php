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
            'shop_type' => ['required', Rule::enum(ShopType::class)],
            'owner_name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'phone:EG', 'unique:users,phone'],
            'email' => ['nullable', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'branch_name' => ['nullable', 'string', 'max:120'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('phone');

        if (is_string($phone) && $phone !== '') {
            try {
                $this->merge(['phone' => (new PhoneNumber($phone, 'EG'))->formatE164()]);
            } catch (\Throwable) {
                // Leave as-is; the phone rule reports it.
            }
        }
    }

    public function toData(): RegisterTenantData
    {
        return new RegisterTenantData(
            shopName: $this->string('shop_name')->toString(),
            shopType: $this->enum('shop_type', ShopType::class) ?? ShopType::Accessories,
            ownerName: $this->string('owner_name')->toString(),
            phone: $this->string('phone')->toString(),
            email: $this->filled('email') ? $this->string('email')->toString() : null,
            password: $this->string('password')->toString(),
            branchName: $this->filled('branch_name') ? $this->string('branch_name')->toString() : 'الفرع الرئيسي',
        );
    }
}
