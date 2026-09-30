<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Requests;

use App\Modules\Services\Enums\MoneySource;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/** Funding an account or cashing one out. */
final class MoveMoneyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('services.fund');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'integer', 'min:1', 'max:1000000000'],
            // Funding airtime: what the distributor was paid (less than the face value).
            'paid' => ['nullable', 'integer', 'min:0', 'max:1000000000'],
            'source' => ['required', Rule::enum(MoneySource::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'المبلغ', 'paid' => 'المدفوع', 'source' => 'منين'];
    }
}
