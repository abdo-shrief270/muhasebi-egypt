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
            'labor_rate' => $rules[$id]->labor_rate ?? 0,
            'parts_rate' => $rules[$id]->parts_rate ?? 0,
            'parts_base' => $rules[$id]->parts_base ?? 'price',
            'profit_rate' => $rules[$id]->profit_rate ?? 0,
            'fixed' => $rules[$id]->fixed ?? 0,
            'warranty_clawback' => $rules[$id]->warranty_clawback ?? false,
            'description' => isset($rules[$id]) ? $rules[$id]->describe() : null,
        ], array_keys($t = $this->staff->withPermission('repairs.update_status')), $t)]);
    }

    /** Rates are basis points (1500 = 15%), fixed is piasters; all zero removes the rule. */
    public function update(Request $request, string $technician, Auditor $audit): JsonResponse
    {
        $user = $technician;
        $name = $this->staff->withPermission('repairs.update_status')[$user]
            ?? throw new DomainRuleException('الفني ده مش موجود أو معندوش صلاحية الصيانة.', 'technician_not_found', 404);
        $data = $request->validate([
            'labor_rate' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'parts_rate' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'parts_base' => ['nullable', Rule::in(['price', 'profit'])],
            'profit_rate' => ['nullable', 'integer', 'min:0', 'max:10000'],
            'fixed' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
            'warranty_clawback' => ['nullable', 'boolean'],
        ], [], ['labor_rate' => 'نسبة المصنعية', 'parts_rate' => 'نسبة القطع', 'profit_rate' => 'نسبة المكسب', 'fixed' => 'المبلغ الثابت']);

        DB::transaction(function () use ($data, $user, $name, $audit): void {
            $rule = new CommissionRule([
                'labor_rate' => (int) ($data['labor_rate'] ?? 0),
                'parts_rate' => (int) ($data['parts_rate'] ?? 0),
                'parts_base' => $data['parts_base'] ?? 'price',
                'profit_rate' => (int) ($data['profit_rate'] ?? 0),
                'fixed' => (int) ($data['fixed'] ?? 0),
                'warranty_clawback' => (bool) ($data['warranty_clawback'] ?? false),
            ]);
            if ($rule->isEmpty()) {
                CommissionRule::query()->where('user_id', $user)->delete();
                $audit->record('repairs.commission', "شال عمولة الفني «{$name}»", tenantId: $this->tenant->idOrFail());

                return;
            }
            $rule = CommissionRule::query()->updateOrCreate(
                ['user_id' => $user],
                ['tenant_id' => $this->tenant->idOrFail(), ...$rule->getAttributes()],
            );
            $audit->record('repairs.commission', "عمولة الفني «{$name}»: {$rule->describe()}".($rule->warranty_clawback ? '، وبتتخصم لو الجهاز رجع في الضمان' : ''), $rule);
        });

        return $this->index();
    }
}
