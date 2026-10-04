<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Http\Controllers;

use App\Modules\OnlineStore\Models\OnlineCoupon;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;

/** The store's discount codes (online_store.manage). A used code is switched off, never deleted. */
final class CouponController
{
    public function index(): JsonResponse
    {
        return response()->json(['data' => $this->all()]);
    }

    public function store(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $data = $this->validated($request, $tenant->idOrFail());
        OnlineCoupon::create($data);

        return response()->json(['data' => $this->all()], 201);
    }

    public function update(Request $request, CurrentTenant $tenant, OnlineCoupon $coupon): JsonResponse
    {
        $coupon->update($this->validated($request, $tenant->idOrFail(), $coupon));

        return response()->json(['data' => $this->all()]);
    }

    public function destroy(OnlineCoupon $coupon): Response
    {
        if ($coupon->uses > 0) {
            throw new DomainRuleException('الكود ده اتستخدم في طلبات؛ وقّفه بدل ما تمسحه.', 'coupon_used');
        }
        $coupon->delete();

        return response()->noContent();
    }

    /** @return array<string, mixed> */
    private function validated(Request $request, string $tenantId, ?OnlineCoupon $coupon = null): array
    {
        $required = $coupon !== null ? 'sometimes' : 'required';
        if ($request->has('code')) {
            $request->merge(['code' => OnlineCoupon::normalize((string) $request->input('code'))]);
        }
        $kind = $request->input('kind', $coupon?->kind);
        $data = $request->validate([
            'code' => [$required, 'string', 'regex:/^[A-Z0-9\-_]{3,32}$/', Rule::unique('online_coupons', 'code')->where('tenant_id', $tenantId)->ignore($coupon?->id)],
            'kind' => [$required, 'in:percent,amount'],
            'value' => [$required, 'integer', 'min:1', $kind === 'percent' ? 'max:90' : 'max:100000000'],
            'min_order' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'max_discount' => ['nullable', 'integer', 'min:1', 'max:100000000'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_uses' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'once_per_phone' => ['sometimes', 'boolean'],
            'is_active' => ['sometimes', 'boolean'],
        ], ['code.regex' => 'الكود حروف إنجليزي وأرقام من 3 لـ 32.'], [
            'code' => 'الكود', 'value' => 'قيمة الخصم', 'min_order' => 'أقل طلب', 'max_discount' => 'أقصى خصم', 'ends_on' => 'آخر يوم', 'max_uses' => 'عدد المرات',
        ]);
        if (($data['kind'] ?? $kind) === 'amount') {
            $data['max_discount'] = null;
        }

        return $data;
    }

    /** @return list<array<string, mixed>> */
    private function all(): array
    {
        return OnlineCoupon::query()->orderByDesc('is_active')->orderByDesc('created_at')->get()->map(fn (OnlineCoupon $c) => $c->toApi())->all();
    }
}
