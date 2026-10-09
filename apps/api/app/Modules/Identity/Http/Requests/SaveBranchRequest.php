<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Enums\Governorate;
use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveBranchRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('branches.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $creating = $this->route('branch') === null;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'address' => ['nullable', 'string', 'max:255'],
            'governorate' => ['nullable', Rule::enum(Governorate::class)],
            'area' => ['nullable', 'string', 'max:80'],
            // Egypt and around it only: a typo (or swapped lat/lng) is caught here.
            'latitude' => ['nullable', 'required_with:longitude', 'numeric', 'between:21,32.5'],
            'longitude' => ['nullable', 'required_with:latitude', 'numeric', 'between:24,37'],
            'phone' => ['nullable', 'phone:EG'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'اسم الفرع', 'governorate' => 'المحافظة', 'area' => 'المنطقة', 'latitude' => 'خط العرض', 'longitude' => 'خط الطول'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
    }
}
