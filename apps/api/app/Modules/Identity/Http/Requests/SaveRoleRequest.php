<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Requests;

use App\Modules\Identity\Models\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveRoleRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('roles.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        /** @var Role|null $role */
        $role = $this->route('role');

        return [
            'name' => ['required', 'string', 'max:80', Rule::unique('roles', 'name')->where('tenant_id', $this->user()?->getAttribute('tenant_id'))->ignore($role?->id)],
            'permissions' => ['present', 'array'],
            'permissions.*' => ['string'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'اسم الدور'];
    }
}
