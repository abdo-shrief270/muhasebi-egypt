<?php

declare(strict_types=1);

namespace App\Modules\ModuleManager\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class ManageModulesRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->getAttribute('is_owner');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [];
    }
}
