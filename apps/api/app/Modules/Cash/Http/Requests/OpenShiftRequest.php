<?php

declare(strict_types=1);

namespace App\Modules\Cash\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class OpenShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('cash.shift');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'opening_cash' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['opening_cash' => 'الكاش اللي في الدرج'];
    }
}
