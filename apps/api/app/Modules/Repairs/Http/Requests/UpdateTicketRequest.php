<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class UpdateTicketRequest extends FormRequest
{
    public function authorize(): bool
    {
        // A discount is money off the bill: it needs the discount permission too.
        return (bool) $this->user()?->can('repairs.update_status')
            && (! $this->has('discount') || $this->user()->can('sales.discount'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'diagnosed_fault_ids' => ['sometimes', 'nullable', 'array', 'max:20'],
            'diagnosed_fault_ids.*' => ['integer', 'distinct'],
            'diagnosis_note' => ['sometimes', 'nullable', 'string', 'max:1000'],
            'labor' => ['sometimes', 'integer', 'min:0', 'max:100000000000'],
            'discount' => ['sometimes', 'integer', 'min:0', 'max:100000000000'],
            'estimate' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'expected_at' => ['sometimes', 'nullable', 'date'],
            'technician_id' => ['sometimes', 'nullable', 'uuid'],
            'imei' => ['sometimes', 'nullable', 'string', 'max:40'],
            'color' => ['sometimes', 'nullable', 'string', 'max:40'],
            'note' => ['sometimes', 'nullable', 'string', 'max:1000'],
        ];
    }
}
