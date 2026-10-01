<?php

declare(strict_types=1);

namespace App\Modules\Installments\Http\Controllers;

use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\Installments\Actions\CancelPlanAction;
use App\Modules\Installments\Actions\CollectInstallmentAction;
use App\Modules\Installments\Actions\CreatePlanAction;
use App\Modules\Installments\Enums\PlanStatus;
use App\Modules\Installments\Http\Requests\CollectInstallmentRequest;
use App\Modules\Installments\Http\Requests\CreatePlanRequest;
use App\Modules\Installments\Http\Resources\InstallmentPlanResource;
use App\Modules\Installments\Models\InstallmentItem;
use App\Modules\Installments\Models\InstallmentPayment;
use App\Modules\Installments\Models\InstallmentPlan;
use App\Modules\Installments\Support\PlanBook;
use App\Modules\Sales\Contracts\CustomerSales;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use App\Support\Text\SearchText;
use App\Support\Time\ShopDay;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class InstallmentController
{
    /** Plans, newest first, with the figures for the top of the page. */
    public function index(Request $request): AnonymousResourceCollection
    {
        $status = (string) $request->query('status', 'active');
        $today = ShopDay::today()->toDateString();
        $plans = InstallmentPlan::query()
            ->with('items')
            ->when(in_array($status, ['active', 'completed', 'cancelled'], true), fn (Builder $q) => $q->where('status', $status))
            ->when($status === 'late', fn (Builder $q) => $q->where('status', PlanStatus::Active)->whereHas('items', fn (Builder $i) => $i->where('due_on', '<', $today)->whereColumn('paid', '<', 'amount')))
            ->when($request->query('customer_id'), fn (Builder $q, $id) => $q->where('customer_id', $id))
            ->when(trim((string) $request->query('q')) !== '', function (Builder $q) use ($request): void {
                $term = trim((string) $request->query('q'));
                $q->where(function (Builder $w) use ($term): void {
                    if (preg_match('/^(?:INS-?)?0*(\d+)$/i', $term, $m)) {
                        $w->orWhere('number', (int) $m[1]);
                    }
                    $digits = preg_replace('/\D/', '', $term);
                    if (strlen($digits) >= 4) {
                        $w->orWhere('customer_phone', 'like', '%'.ltrim($digits, '0').'%');
                    }
                    $w->orWhere('customer_name', 'ilike', SearchText::like($term));
                });
            })
            ->orderByDesc('number')
            ->paginate(30);

        return InstallmentPlanResource::collection($plans)->additional(['meta' => ['summary' => $this->summary()]]);
    }

    /** Installments still open and due by the end of the week (late ones first), for collecting. */
    public function due(Request $request): JsonResponse
    {
        $today = ShopDay::today();
        $until = match ((string) $request->query('when', 'week')) {
            'late' => $today->copy()->subDay(),
            'today' => $today,
            'month' => $today->copy()->addDays(30),
            default => $today->copy()->addDays(7),
        };
        $items = InstallmentItem::query()
            ->with('plan')
            ->whereHas('plan', fn (Builder $q) => $q->where('status', PlanStatus::Active))
            ->whereColumn('paid', '<', 'amount')
            ->where('due_on', '<=', $until->toDateString())
            ->orderBy('due_on')
            ->limit(300)
            ->get();

        return response()->json(['data' => $items->map(fn (InstallmentItem $i) => [
            ...InstallmentPlanResource::item($i, $today),
            'plan_id' => $i->plan_id,
            'plan_reference' => $i->plan->reference(),
            'plan_remaining' => $i->plan->remaining(),
            'customer_id' => $i->plan->customer_id,
            'customer_name' => $i->plan->customer_name,
            'customer_phone' => $i->plan->customer_phone,
        ])->values(), 'meta' => ['summary' => $this->summary()]]);
    }

    /** How much of a customer's debt (or a sale's آجل) can still go into a new plan. */
    public function available(Request $request, CustomerAccounts $customers, CustomerSales $sales, PlanBook $book): JsonResponse
    {
        $request->validate(['customer_id' => ['required', 'uuid'], 'sale_id' => ['nullable', 'uuid']]);
        $customer = $customers->find((string) $request->query('customer_id')) ?? throw new DomainRuleException('العميل مش موجود.', 'customer_not_found', 404);
        $planned = $book->outstandingFor($customer->id);
        $available = max(0, $customer->balance - $planned);
        $sale = null;
        if ($request->query('sale_id')) {
            $credit = $sales->creditOf((string) $request->query('sale_id'));
            if ($credit !== null && $credit['customer_id'] === $customer->id) {
                $onSale = (int) InstallmentPlan::query()->where('sale_id', $request->query('sale_id'))->where('status', '!=', PlanStatus::Cancelled)->sum('principal');
                $sale = ['reference' => $credit['reference'], 'credit' => $credit['credit'], 'available' => max(0, min($available, $credit['credit'] - $onSale))];
            }
        }

        return response()->json(['data' => [
            'customer' => $customer->toArray(),
            'in_plans' => $planned,
            'available' => $available,
            'sale' => $sale,
        ]]);
    }

    public function show(InstallmentPlan $plan): InstallmentPlanResource
    {
        return new InstallmentPlanResource($plan->load(['items', 'payments']));
    }

    public function store(CreatePlanRequest $request, CreatePlanAction $action, CurrentTenant $tenant, CurrentBranch $branch): JsonResponse
    {
        $plan = $action->handle($tenant->idOrFail(), $branch->idOrFail(), $request->user(), $request->validated());

        return (new InstallmentPlanResource($plan->load(['items', 'payments'])))->response()->setStatusCode(201);
    }

    public function pay(CollectInstallmentRequest $request, InstallmentPlan $plan, CollectInstallmentAction $action, CurrentBranch $branch): InstallmentPlanResource
    {
        $plan = $action->handle($plan, $branch->idOrFail(), (int) $request->validated('amount'), (string) $request->validated('method'));

        return new InstallmentPlanResource($plan->load(['items', 'payments']));
    }

    public function cancel(Request $request, InstallmentPlan $plan, CancelPlanAction $action): InstallmentPlanResource
    {
        $data = $request->validate(['reason' => ['nullable', 'string', 'max:300']]);

        return new InstallmentPlanResource($action->handle($plan, $request->user(), $data['reason'] ?? null)->load(['items', 'payments']));
    }

    /**
     * @return array{active: int, outstanding: int, late_amount: int, late_plans: int, due_today: int, collected_month: int}
     */
    private function summary(): array
    {
        $today = ShopDay::today()->toDateString();
        $active = InstallmentPlan::query()->where('status', PlanStatus::Active);
        $open = InstallmentItem::query()
            ->whereHas('plan', fn (Builder $q) => $q->where('status', PlanStatus::Active))
            ->whereColumn('paid', '<', 'amount');

        return [
            'active' => (clone $active)->count(),
            'outstanding' => (int) (clone $active)->sum(DB::raw('total - paid')),
            'late_amount' => (int) (clone $open)->where('due_on', '<', $today)->sum(DB::raw('amount - paid')),
            'late_plans' => (int) (clone $open)->where('due_on', '<', $today)->distinct()->count('plan_id'),
            'due_today' => (int) (clone $open)->where('due_on', $today)->sum(DB::raw('amount - paid')),
            'collected_month' => (int) InstallmentPayment::query()->where('created_at', '>=', Carbon::now()->startOfMonth())->sum('amount'),
        ];
    }
}
