<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Propaganistas\LaravelPhone\PhoneNumber;

final class LoginRequest extends FormRequest
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
            'phone' => ['required', 'string'],
            'password' => ['required', 'string'],
            'device_name' => ['required', 'string', 'max:120'],
        ];
    }

    public function normalizedPhone(): string
    {
        $phone = $this->string('phone')->toString();

        try {
            return (new PhoneNumber($phone, 'EG'))->formatE164();
        } catch (\Throwable) {
            return $phone;
        }
    }
}
