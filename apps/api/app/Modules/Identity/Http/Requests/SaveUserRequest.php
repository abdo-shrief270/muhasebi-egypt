<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Http\Requests\Concerns\NormalizesPhone;
use App\Modules\Identity\Models\User;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

final class SaveUserRequest extends FormRequest
{
    use NormalizesPhone;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('users.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var User|null $editing */
        $editing = $this->route('user');
        $creating = $editing === null;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120'],
            'phone' => [$creating ? 'required' : 'sometimes', 'phone:EG', Rule::unique('users', 'phone')->ignore($editing?->id)],
            'email' => ['nullable', 'email', 'max:190', Rule::unique('users', 'email')->ignore($editing?->id)],
            'password' => [$creating ? 'required' : 'nullable', Password::min(8)],
            'role_id' => ['sometimes', 'nullable', 'integer'],
            'branch_ids' => [$creating ? 'required' : 'sometimes', 'array', 'min:1'],
            'branch_ids.*' => ['uuid'],
            'is_active' => ['sometimes', 'boolean'],
        ];
    }

    public function attributes(): array
    {
        return ['branch_ids' => 'الفروع', 'role_id' => 'الدور'];
    }

    protected function prepareForValidation(): void
    {
        $this->normalizePhone();
    }
}
