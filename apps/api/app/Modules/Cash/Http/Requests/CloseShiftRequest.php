<?php

declare(strict_types=1);

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Enums\Method;
use App\Modules\Cash\Models\CashShift;
use Illuminate\Foundation\Http\FormRequest;

final class CloseShiftRequest extends FormRequest
{
    public function authorize(): bool
    {
        $shift = $this->route('shift');
        $user = $this->user();

        // Your own shift, or anyone's for whoever manages the cash (a shift left open overnight).
        return $shift instanceof CashShift && $user !== null
            && ($user->can('cash.manage') || ($user->can('cash.shift') && $shift->user_id === $user->getAuthIdentifier()));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = [
            'counted' => ['required', 'array:'.implode(',', array_column(Method::cases(), 'value'))],
            'counted.cash' => ['required', 'integer', 'min:0', 'max:100000000000'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
        foreach (Method::cases() as $method) {
            $rules["counted.{$method->value}"] ??= ['nullable', 'integer', 'min:0', 'max:100000000000'];
        }

        return $rules;
    }

    public function attributes(): array
    {
        return ['counted.cash' => 'الكاش اللي اتعد'];
    }

    /**
     * @return array<string, int>
     */
    public function counted(): array
    {
        return array_map('intval', array_filter((array) $this->validated('counted'), fn ($v) => $v !== null));
    }
}
