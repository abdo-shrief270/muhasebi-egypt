<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Requests;

use App\Modules\Suppliers\Models\Supplier;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveSupplierRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('suppliers.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $supplier = $this->route('supplier');
        $creating = ! $supplier instanceof Supplier;

        return [
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:120', Rule::unique('suppliers', 'name')
                ->where(fn (Builder $q) => $q->where('tenant_id', app(CurrentTenant::class)->idOrFail()))
                ->ignore($creating ? null : $supplier->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'is_active' => ['sometimes', 'boolean'],
            // Only when adding: what the shop already owed the supplier (negative = the supplier owes the shop).
            'opening_balance' => [$creating ? 'nullable' : 'prohibited', 'integer', 'min:-100000000000', 'max:100000000000'],
        ];
    }

    public function attributes(): array
    {
        return ['name' => 'اسم المورد', 'phone' => 'الموبايل', 'opening_balance' => 'الرصيد الافتتاحي'];
    }

    public function messages(): array
    {
        return ['name.unique' => 'فيه مورد بالاسم ده بالفعل.'];
    }

    /**
     * @return array{name?: string, phone?: string|null, notes?: string|null, is_active?: bool}
     */
    public function supplierData(): array
    {
        /** @var array{name?: string, phone?: string|null, notes?: string|null, is_active?: bool} */
        return $this->safe()->only(['name', 'phone', 'notes', 'is_active']);
    }
}
