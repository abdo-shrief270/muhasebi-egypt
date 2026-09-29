<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class DeliverTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('repairs.deliver');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'payments' => ['nullable', 'array', 'max:5'],
            'payments.*.method' => ['required', Rule::in(['cash', 'card', 'wallet', 'instapay', 'credit'])],
            'payments.*.amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'warranty_days' => ['nullable', 'integer', 'min:0', 'max:730'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return list<array{method: string, amount: int}>
     */
    public function payments(): array
    {
        return array_values(array_map(fn (array $p) => ['method' => (string) $p['method'], 'amount' => (int) $p['amount']], $this->validated('payments') ?? []));
    }
}
