<?php

declare(strict_types=1);

namespace App\Modules\Reports\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class RunReportRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('reports.view');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
            // A branch id, or "all" for every branch the user may see.
            'branch' => ['nullable', 'string', 'max:36'],
            'options' => ['nullable', 'array'],
            'options.*' => ['string', 'max:30'],
        ];
    }

    public function attributes(): array
    {
        return ['from' => 'من', 'to' => 'لـ'];
    }
}
