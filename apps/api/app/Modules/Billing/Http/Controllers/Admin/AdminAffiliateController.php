<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Models\Affiliate;
use App\Modules\Billing\Models\AffiliateCommission;
use App\Modules\Billing\Models\AffiliatePayout;
use App\Modules\Billing\Models\AffiliateReferral;
use App\Modules\Billing\Models\PlatformAdmin;
use App\Modules\Billing\Support\AdminLog;
use App\Modules\Billing\Support\Affiliates;
use App\Modules\Identity\Contracts\ShopDirectory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** «الشركاء» for platform admins: partners, what they brought and earned, and paying them. */
final class AdminAffiliateController
{
    public function __construct(
        private readonly Affiliates $affiliates,
        private readonly AdminLog $log,
        private readonly ShopDirectory $shops,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $list = Affiliate::query()
            ->when($q !== '', fn (Builder $b) => $b->where(fn (Builder $w) => $w->where('name', 'ilike', "%{$q}%")->orWhere('phone', 'like', "%{$q}%")->orWhere('code', strtoupper($q))))
            ->when($request->filled('status'), fn (Builder $b) => $b->where('status', $request->query('status')))
            ->latest()->limit(300)->get();
        $ids = $list->pluck('id');
        $signups = AffiliateReferral::query()->whereIn('affiliate_id', $ids)->selectRaw('affiliate_id, count(*) as n, count(first_paid_at) as paying')->groupBy('affiliate_id')->get()->keyBy('affiliate_id');
        $earned = AffiliateCommission::query()->whereIn('affiliate_id', $ids)->where('status', '<>', 'void')
            ->selectRaw("affiliate_id, sum(amount) as total, sum(case when status = 'paid' then amount else 0 end) as paid")->groupBy('affiliate_id')->get()->keyBy('affiliate_id');

        return response()->json(['data' => $list->map(fn (Affiliate $a) => [
            ...$this->row($a),
            'signups' => (int) ($signups[$a->id]->n ?? 0),
            'paying' => (int) ($signups[$a->id]->paying ?? 0),
            'earned' => (int) ($earned[$a->id]->total ?? 0),
            'paid' => (int) ($earned[$a->id]->paid ?? 0),
        ])->all(), 'meta' => [
            'requested_payouts' => AffiliatePayout::query()->where('status', 'requested')->count(),
            'owed' => (int) AffiliateCommission::query()->where('status', 'pending')->sum('amount'),
        ]]);
    }

    public function show(Affiliate $affiliate): JsonResponse
    {
        $referrals = AffiliateReferral::query()->where('affiliate_id', $affiliate->id)->latest()->get();
        $commissions = AffiliateCommission::query()->where('affiliate_id', $affiliate->id)->latest()->limit(300)->get();
        $names = $this->shops->findMany(array_values(array_unique([...$referrals->pluck('tenant_id')->all(), ...$commissions->pluck('tenant_id')->all()])));

        return response()->json(['data' => [
            ...$this->row($affiliate),
            'email' => $affiliate->email,
            'payout_method' => $affiliate->payout_method,
            'payout_account' => $affiliate->payout_account,
            'payout_name' => $affiliate->payout_name,
            'admin_note' => $affiliate->admin_note,
            'balances' => $this->affiliates->balances($affiliate),
            'referrals' => $referrals->map(fn (AffiliateReferral $r) => [
                'tenant_id' => $r->tenant_id,
                'shop' => $names[$r->tenant_id]->name ?? '—',
                'code' => $names[$r->tenant_id]->code ?? null,
                'registered_at' => $r->created_at->toIso8601String(),
                'first_paid_at' => $r->first_paid_at?->toIso8601String(),
                'commission_until' => $r->commission_until?->toIso8601String(),
            ])->all(),
            'commissions' => $commissions->map(fn (AffiliateCommission $c) => [
                'id' => $c->id,
                'shop' => $names[$c->tenant_id]->name ?? '—',
                'invoice' => $c->invoice_reference,
                'base' => $c->base,
                'rate_percent' => $c->rate_bp / 100,
                'amount' => $c->amount,
                'state' => $c->state(),
                'void_reason' => $c->void_reason,
                'available_at' => $c->available_at->toIso8601String(),
                'created_at' => $c->created_at->toIso8601String(),
            ])->all(),
            'payouts' => $affiliate->payouts()->latest()->get()->map(fn (AffiliatePayout $p) => $this->payout($p))->all(),
        ]]);
    }

    public function update(Request $request, Affiliate $affiliate): JsonResponse
    {
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['active', 'suspended'])],
            'rate_percent' => ['nullable', 'numeric', 'min:0', 'max:60'],
            'admin_note' => ['nullable', 'string', 'max:2000'],
        ]);
        if (array_key_exists('rate_percent', $data)) {
            $data['rate_bp'] = $data['rate_percent'] === null ? null : (int) round((float) $data['rate_percent'] * 100);
            unset($data['rate_percent']);
        }
        $affiliate->fill($data)->save();
        if (($data['status'] ?? null) === 'suspended') {
            $affiliate->tokens()->delete();
        }
        $this->log->record('affiliate_updated', subjectId: $affiliate->id, details: $data);

        return $this->show($affiliate->refresh());
    }

    public function payouts(Request $request): JsonResponse
    {
        $status = (string) $request->query('status', 'requested');

        return response()->json(['data' => AffiliatePayout::query()->with('affiliate')
            ->when($status !== 'all', fn (Builder $b) => $b->where('status', $status))
            ->latest()->limit(200)->get()
            ->map(fn (AffiliatePayout $p) => [...$this->payout($p), 'affiliate' => ['id' => $p->affiliate->id, 'name' => $p->affiliate->name, 'phone' => $p->affiliate->phone, 'code' => $p->affiliate->code]])
            ->all()]);
    }

    public function markPaid(Request $request, AffiliatePayout $payout): JsonResponse
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:80']], attributes: ['reference' => 'رقم العملية']);
        $payout = $this->affiliates->markPaid($payout, $data['reference'], $this->adminName($request));
        $this->log->record('affiliate_payout_paid', subjectId: $payout->id, details: ['amount' => $payout->amount, 'reference' => $data['reference']]);

        return response()->json(['data' => $this->payout($payout)]);
    }

    public function reject(Request $request, AffiliatePayout $payout): JsonResponse
    {
        $data = $request->validate(['note' => ['required', 'string', 'max:255']], attributes: ['note' => 'السبب']);
        $payout = $this->affiliates->reject($payout, $data['note'], $this->adminName($request));
        $this->log->record('affiliate_payout_rejected', subjectId: $payout->id, details: ['note' => $data['note']]);

        return response()->json(['data' => $this->payout($payout)]);
    }

    public function voidCommission(Request $request, AffiliateCommission $commission): JsonResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']], attributes: ['reason' => 'السبب']);
        $this->affiliates->void($commission, $data['reason']);
        $this->log->record('affiliate_commission_void', $commission->tenant_id, $commission->id, ['amount' => $commission->amount, 'reason' => $data['reason']]);

        return $this->show(Affiliate::query()->findOrFail($commission->affiliate_id));
    }

    /** @return array<string, mixed> */
    private function row(Affiliate $a): array
    {
        return [
            'id' => $a->id,
            'name' => $a->name,
            'phone' => $a->phone,
            'code' => $a->code,
            'status' => $a->status,
            'channel' => $a->channel,
            'clicks' => $a->clicks,
            'rate_percent' => $a->rate() / 100,
            'custom_rate' => $a->rate_bp !== null,
            'last_login_at' => $a->last_login_at?->toIso8601String(),
            'created_at' => $a->created_at->toIso8601String(),
        ];
    }

    /** @return array<string, mixed> */
    private function payout(AffiliatePayout $p): array
    {
        return [
            'id' => $p->id,
            'amount' => $p->amount,
            'method' => $p->method,
            'method_label' => Affiliate::PAYOUT_METHODS[$p->method] ?? $p->method,
            'account' => $p->account,
            'account_name' => $p->account_name,
            'status' => $p->status,
            'reference' => $p->reference,
            'note' => $p->note,
            'decided_by_name' => $p->decided_by_name,
            'created_at' => $p->created_at->toIso8601String(),
            'decided_at' => $p->decided_at?->toIso8601String(),
        ];
    }

    private function adminName(Request $request): ?string
    {
        $user = $request->user();

        return $user instanceof PlatformAdmin ? $user->name : null;
    }
}
