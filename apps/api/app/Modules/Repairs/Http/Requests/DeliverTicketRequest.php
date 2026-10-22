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
            // Item by item: a part fitted (its id) or the labor (no id), each with its days.
            'warranties' => ['nullable', 'array', 'max:50'],
            'warranties.*.part_id' => ['nullable', 'integer'],
            'warranties.*.days' => ['required', 'integer', 'min:0', 'max:730'],
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

    /** @return list<array{part_id: int|null, days: int}>|null */
    public function warranties(): ?array
    {
        $items = $this->validated('warranties');

        return $items === null ? null : array_values(array_map(fn (array $w) => [
            'part_id' => isset($w['part_id']) ? (int) $w['part_id'] : null,
            'days' => (int) $w['days'],
        ], $items));
    }
}
