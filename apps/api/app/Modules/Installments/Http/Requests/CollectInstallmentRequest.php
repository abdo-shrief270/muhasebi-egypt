<?php

declare(strict_types=1);

namespace App\Modules\Installments\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class CollectInstallmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('installments.collect');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'method' => ['required', 'string', 'in:cash,card,wallet,instapay'],
        ];
    }
}
