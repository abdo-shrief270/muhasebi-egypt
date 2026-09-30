<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Http\Requests;

use App\Modules\SupplierReturns\Contracts\ReturnReason;
use App\Modules\SupplierReturns\Enums\SourceType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class AddToBinRequest extends FormRequest
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
            'variant_id' => ['required', 'uuid'],
            'qty' => ['required', 'integer', 'min:1', 'max:100000'],
            'serials' => ['nullable', 'array'],
            'serials.*' => ['string', 'max:48'],
            'reason' => ['required', Rule::enum(ReturnReason::class)],
            'note' => ['nullable', 'string', 'max:500'],
            'source' => ['nullable', 'array'],
            'source.type' => ['required_with:source', Rule::enum(SourceType::class)],
            'source.id' => ['required_with:source', 'uuid'],
        ];
    }

    public function attributes(): array
    {
        return ['qty' => 'الكمية', 'reason' => 'السبب', 'note' => 'الملاحظة'];
    }

    /**
     * @return array{type: string, id: string}|null
     */
    public function source(): ?array
    {
        $source = $this->validated('source');

        return is_array($source) ? ['type' => (string) $source['type'], 'id' => (string) $source['id']] : null;
    }
}
