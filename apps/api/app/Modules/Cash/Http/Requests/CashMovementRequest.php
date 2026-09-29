<?php

declare(strict_types=1);

namespace App\Modules\Cash\Http\Requests;

use App\Modules\Cash\Contracts\ExpenseCategory;
use App\Modules\Cash\Enums\MovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class CashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can($this->input('type') === MovementType::Expense->value ? 'cash.expenses' : 'cash.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in([MovementType::Expense->value, MovementType::Deposit->value, MovementType::Withdrawal->value])],
            'amount' => ['required', 'integer', 'min:1', 'max:100000000000'],
            'category' => ['nullable', 'required_if:type,expense', Rule::enum(ExpenseCategory::class)],
            'note' => ['nullable', 'required_unless:type,expense', 'string', 'max:500'],
        ];
    }

    public function attributes(): array
    {
        return ['amount' => 'المبلغ', 'category' => 'نوع المصروف', 'note' => 'السبب'];
    }
}
