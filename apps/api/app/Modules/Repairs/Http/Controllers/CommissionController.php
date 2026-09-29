<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Controllers;

use App\Modules\Identity\Contracts\StaffDirectory;
use App\Modules\Repairs\Models\CommissionRule;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Each technician's commission rule (repairs.settings). */
final class CommissionController
{
    public function __construct(
        private readonly StaffDirectory $staff,
        private readonly CurrentTenant $tenant,
    ) {}

    public function index(): JsonResponse
    {
        $rules = CommissionRule::query()->get()->keyBy('user_id');

        return response()->json(['data' => array_map(fn (string $id, string $name): array => [
            'user_id' => $id,
            'name' => $name,
            'type' => $rules[$id]->type ?? 'none',
            'value' => $rules[$id]->value ?? null,
            'base' => $rules[$id]->base ?? 'labor',
            'description' => isset($rules[$id]) ? $rules[$id]->describe() : null,
        ], array_keys($t = $this->staff->withPermission('repairs.update_status')), $t)]);
    }

    /** type none removes the rule; percent values are basis points (1500 = 15%). */
    public function update(Request $request, string $technician, Auditor $audit): JsonResponse
    {
        $user = $technician;
        $name = $this->staff->withPermission('repairs.update_status')[$user]
            ?? throw new DomainRuleException('الفني ده مش موجود أو معندوش صلاحية الصيانة.', 'technician_not_found', 404);
        $data = $request->validate([
            'type' => ['required', Rule::in(['none', 'percent', 'fixed'])],
            'value' => ['required_unless:type,none', 'nullable', 'integer', 'min:0', $request->input('type') === 'percent' ? 'max:10000' : 'max:100000000000'],
            'base' => ['nullable', Rule::in(['labor', 'profit'])],
        ], [], ['value' => 'قيمة العمولة']);

        DB::transaction(function () use ($data, $user, $name, $audit): void {
            if ($data['type'] === 'none') {
                CommissionRule::query()->where('user_id', $user)->delete();
                $audit->record('repairs.commission', "شال عمولة الفني «{$name}»", tenantId: $this->tenant->idOrFail());

                return;
            }
            $rule = CommissionRule::query()->updateOrCreate(
                ['user_id' => $user],
                ['tenant_id' => $this->tenant->idOrFail(), 'type' => $data['type'], 'value' => (int) $data['value'], 'base' => $data['base'] ?? 'labor'],
            );
            $audit->record('repairs.commission', "عمولة الفني «{$name}»: {$rule->describe()}", $rule);
        });

        return $this->index();
    }
}
