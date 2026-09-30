<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Http\Requests;

use App\Modules\SupplierReturns\Actions\SettleNoteAction;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SettleNoteRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('supplier_returns.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'items' => ['present', 'array'],
            'items.*.id' => ['required', 'uuid', 'distinct'],
            'items.*.accepted_qty' => ['required', 'integer', 'min:0'],
            'items.*.replacement_serials' => ['nullable', 'array'],
            'items.*.replacement_serials.*' => ['string', 'max:48'],
            'resolution' => ['nullable', Rule::in(SettleNoteAction::RESOLUTIONS)],
            'refund_method' => ['nullable', Rule::in(SettleNoteAction::REFUND_METHODS)],
            'rejected_action' => ['nullable', Rule::in(SettleNoteAction::REJECTED_ACTIONS)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /** @return array<string, int> */
    public function accepted(): array
    {
        $out = [];
        foreach ($this->validated('items') as $line) {
            $out[(string) $line['id']] = (int) $line['accepted_qty'];
        }

        return $out;
    }

    /** @return array<string, list<string>> */
    public function replacementSerials(): array
    {
        $out = [];
        foreach ($this->validated('items') as $line) {
            if (! empty($line['replacement_serials'])) {
                $out[(string) $line['id']] = array_values(array_map('strval', $line['replacement_serials']));
            }
        }

        return $out;
    }
}
