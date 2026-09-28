<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhone;
use Illuminate\Foundation\Http\FormRequest;

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
            'phone' => ['nullable', 'phone:EG'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'اسم الفرع'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
    }
}
