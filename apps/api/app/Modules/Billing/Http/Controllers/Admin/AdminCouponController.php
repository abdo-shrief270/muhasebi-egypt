<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Models\BillingCoupon;
use App\Modules\Billing\Models\WalletTransaction;
use App\Modules\Billing\Support\AdminLog;
use App\Modules\Billing\Support\Wallet;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/** Platform admins: subscription coupons, and credit / points given to a shop by hand. */
final class AdminCouponController
{
    public function __construct(private readonly AdminLog $log) {}

    public function index(): JsonResponse
    {
        return response()->json(['data' => BillingCoupon::query()->orderByDesc('is_active')->orderByDesc('created_at')->get()->map(fn (BillingCoupon $c) => $c->toApi())->all()]);
    }

    public function store(Request $request): JsonResponse
    {
        $coupon = BillingCoupon::create($this->validated($request));
        $this->log->record('coupon_created', null, $coupon->id, ['code' => $coupon->code, 'kind' => $coupon->kind, 'value' => $coupon->value]);

        return response()->json(['data' => $coupon->toApi()], 201);
    }

    public function update(Request $request, string $coupon): JsonResponse
    {
        $model = BillingCoupon::query()->findOrFail($coupon);
        $model->update($request->validate([
            'is_active' => ['sometimes', 'boolean'],
            'ends_on' => ['nullable', 'date'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ]));
        $this->log->record('coupon_updated', null, $model->id, ['code' => $model->code, 'is_active' => $model->is_active]);

        return response()->json(['data' => $model->toApi()]);
    }

    /** Credit (piasters) or points into a shop's wallet: a gift, compensation. */
    public function grant(Request $request, string $tenant, Wallet $wallet, CurrentTenant $current, Auditor $audit, ShopDirectory $shops): JsonResponse
    {
        abort_if($shops->find($tenant) === null, 404);
        $data = $request->validate([
            'unit' => ['required', 'in:credit,points'],
            'amount' => ['required', 'integer', 'not_in:0', 'min:-100000000', 'max:100000000'],
            'note' => ['required', 'string', 'max:255'],
        ], attributes: ['amount' => 'القيمة', 'note' => 'السبب']);
        $tx = DB::transaction(fn (): WalletTransaction => $wallet->post($tenant, $data['unit'], 'admin', (int) $data['amount'], note: $data['note']));
        $this->log->record('wallet_granted', $tenant, details: [...$data, 'transaction' => $tx->id]);
        $what = $data['unit'] === 'credit' ? number_format($data['amount'] / 100, 2).' ج رصيد' : "{$data['amount']} نقطة";
        $current->runAs($tenant, fn () => $audit->record('billing.wallet_admin', "إدارة محاسبي: {$what} — {$data['note']}", tenantId: $tenant));

        return response()->json(['data' => $tx->toApi()], 201);
    }

    /** @return array<string, mixed> */
    private function validated(Request $request): array
    {
        if ($request->has('code')) {
            $request->merge(['code' => mb_strtoupper(trim((string) $request->input('code')))]);
        }
        $kind = $request->input('kind');

        return $request->validate([
            'code' => ['required', 'string', 'regex:/^[A-Z0-9\-_]{3,32}$/', Rule::unique('billing_coupons', 'code')],
            'kind' => ['required', Rule::in(BillingCoupon::KINDS)],
            'value' => ['required', 'integer', 'min:1', $kind === 'percent' ? 'max:100' : 'max:100000000'],
            'months' => ['sometimes', 'integer', 'min:1', 'max:24'],
            'new_shops_only' => ['sometimes', 'boolean'],
            'starts_on' => ['nullable', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'max_redemptions' => ['nullable', 'integer', 'min:1'],
            'note' => ['nullable', 'string', 'max:255'],
        ], ['code.regex' => 'الكود حروف إنجليزي وأرقام من 3 لـ 32.']);
    }
}
