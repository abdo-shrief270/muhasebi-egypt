<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Requests;

use App\Modules\Services\Enums\AccountKind;
use App\Modules\Services\Enums\Provider;
use App\Modules\Services\Enums\WithdrawFeeMode;
use App\Modules\Services\Models\ServiceAccount;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class SaveAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('services.settings');
    }

    public function account(): ?ServiceAccount
    {
        $account = $this->route('account');

        return $account instanceof ServiceAccount ? $account : null;
    }

    public function kind(): AccountKind
    {
        return $this->account()->kind ?? AccountKind::from((string) $this->input('kind'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $account = $this->account();
        $creating = $account === null;
        $kind = $creating ? AccountKind::tryFrom((string) $this->input('kind')) : $account->kind;
        $max = 'max:1000000000';

        return [
            'kind' => [$creating ? 'required' : 'prohibited', Rule::enum(AccountKind::class)],
            'provider' => [$creating ? 'required' : 'sometimes', Rule::in(array_map(fn (Provider $p) => $p->value, Provider::for($kind ?? AccountKind::Wallet)))],
            'name' => [$creating ? 'required' : 'sometimes', 'string', 'max:80', Rule::unique('service_accounts', 'name')
                ->where(fn (Builder $q) => $q->where('tenant_id', app(CurrentTenant::class)->idOrFail())->where('branch_id', $account->branch_id ?? app(CurrentBranch::class)->id()))
                ->ignore($account?->id)],
            'phone' => ['nullable', 'string', 'max:20'],
            'daily_limit' => ['nullable', 'integer', 'min:1', $max],
            'withdraw_fee_mode' => ['sometimes', Rule::enum(WithdrawFeeMode::class)],
            'is_active' => ['sometimes', 'boolean'],
            'opening_balance' => [$creating ? 'nullable' : 'prohibited', 'integer', 'min:0', $max],
            'opening_cost' => [$creating ? 'nullable' : 'prohibited', 'integer', 'min:0', 'lte:opening_balance'],
            'fees' => ['sometimes', 'array'],
            'fees.*.percent' => ['sometimes', 'integer', 'min:0', 'max:10000'],
            'fees.*.fixed' => ['sometimes', 'integer', 'min:0', $max],
            'fees.*.min' => ['sometimes', 'integer', 'min:0', $max],
            'fees.*.max' => ['nullable', 'integer', 'min:0', $max],
            'fees.*.round_to' => ['sometimes', 'integer', 'min:0', 'max:100000'],
        ];
    }

    public function after(): array
    {
        return [function ($validator): void {
            foreach ((array) $this->input('fees', []) as $op => $rule) {
                if (isset($rule['max'], $rule['min']) && (int) $rule['max'] < (int) $rule['min']) {
                    $validator->errors()->add("fees.{$op}.max", 'أقصى عمولة لازم تبقى أكبر من أقل عمولة.');
                }
            }
        }];
    }

    public function attributes(): array
    {
        return ['name' => 'الاسم', 'provider' => 'الشركة', 'daily_limit' => 'الحد اليومي', 'opening_balance' => 'الرصيد الافتتاحي', 'opening_cost' => 'تكلفة الرصيد'];
    }

    public function messages(): array
    {
        return ['name.unique' => 'فيه حساب بالاسم ده في الفرع.', 'opening_cost.lte' => 'التكلفة مش ممكن تبقى أكبر من الرصيد.'];
    }

    /**
     * @return array<string, mixed>
     */
    public function accountData(): array
    {
        return array_intersect_key($this->validated(), array_flip(['kind', 'provider', 'name', 'phone', 'daily_limit', 'withdraw_fee_mode', 'is_active']));
    }
}
