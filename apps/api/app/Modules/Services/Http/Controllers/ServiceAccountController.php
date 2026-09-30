<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Controllers;

use App\Modules\Services\Actions\MoveAccountMoneyAction;
use App\Modules\Services\Actions\SaveAccountAction;
use App\Modules\Services\Enums\AccountKind;
use App\Modules\Services\Enums\MoneySource;
use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Enums\Provider;
use App\Modules\Services\Enums\WithdrawFeeMode;
use App\Modules\Services\Http\Requests\MoveMoneyRequest;
use App\Modules\Services\Http\Requests\SaveAccountRequest;
use App\Modules\Services\Http\Resources\ServiceAccountResource;
use App\Modules\Services\Http\Resources\ServiceTransactionResource;
use App\Modules\Services\Models\ServiceAccount;
use App\Modules\Services\Models\ServiceTransaction;
use App\Modules\Services\Support\DailyUsage;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ServiceAccountController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly CurrentBranch $branch,
    ) {}

    /** The branch's accounts (active ones unless ?all=1), with today's use and the fee rules; and today's totals. */
    public function index(Request $request): JsonResponse
    {
        $branchId = $this->branch->idOrFail();
        $accounts = ServiceAccount::query()
            ->with('feeRules')
            ->where('branch_id', $branchId)
            ->unless($request->boolean('all'), fn ($q) => $q->where('is_active', true))
            ->orderByDesc('is_active')
            ->orderBy('kind')
            ->orderBy('name')
            ->get();
        $used = DailyUsage::today($accounts->pluck('id')->all());
        $accounts->each(fn (ServiceAccount $a) => $a->setAttribute('today_used', $used[$a->id] ?? 0));

        $today = ServiceTransaction::query()
            ->where('branch_id', $branchId)
            ->whereIn('type', array_map(fn (OperationType $t) => $t->value, OperationType::customer()))
            ->where('created_at', '>=', CarbonImmutable::now(DailyUsage::TZ)->startOfDay()->utc())
            ->selectRaw('count(*) filter (where reverses_id is null) - count(*) filter (where reverses_id is not null) as operations, coalesce(sum(fee), 0) as fees')
            ->first();

        return response()->json([
            'data' => ServiceAccountResource::collection($accounts),
            'meta' => ['today' => ['operations' => (int) $today?->getAttribute('operations'), 'fees' => (int) $today?->getAttribute('fees')]],
        ]);
    }

    /** Choices for the account form and the counter. */
    public function options(): JsonResponse
    {
        $kinds = array_map(fn (AccountKind $k) => [
            'value' => $k->value,
            'label' => $k->label(),
            'providers' => array_map(fn (Provider $p) => ['value' => $p->value, 'label' => $p->label($k)], Provider::for($k)),
            'operations' => array_map(fn (OperationType $o) => ['value' => $o->value, 'label' => $o->label()], $k->operations()),
        ], AccountKind::cases());

        return response()->json(['data' => [
            'kinds' => $kinds,
            'types' => array_map(fn (OperationType $o) => ['value' => $o->value, 'label' => $o->label()], OperationType::cases()),
            'fee_modes' => array_map(fn (WithdrawFeeMode $m) => ['value' => $m->value, 'label' => $m->label()], WithdrawFeeMode::cases()),
            'sources' => array_map(fn (MoneySource $s) => ['value' => $s->value, 'label' => $s->label()], MoneySource::cases()),
        ]]);
    }

    public function show(ServiceAccount $account): ServiceAccountResource
    {
        $account->load('feeRules')->setAttribute('today_used', DailyUsage::today([$account->id])[$account->id] ?? 0);

        return new ServiceAccountResource($account);
    }

    public function store(SaveAccountRequest $request, SaveAccountAction $action): JsonResponse
    {
        $account = $action->handle(
            $this->tenant->idOrFail(),
            $this->branch->idOrFail(),
            $request->accountData(),
            $request->validated('fees'),
            opening: (int) $request->validated('opening_balance', 0),
            openingCost: $request->validated('opening_cost') !== null ? (int) $request->validated('opening_cost') : null,
        );

        return (new ServiceAccountResource($account))->response()->setStatusCode(201);
    }

    public function update(SaveAccountRequest $request, ServiceAccount $account, SaveAccountAction $action): ServiceAccountResource
    {
        return new ServiceAccountResource($action->handle($this->tenant->idOrFail(), $account->branch_id, $request->accountData(), $request->validated('fees'), $account));
    }

    public function fund(MoveMoneyRequest $request, ServiceAccount $account, MoveAccountMoneyAction $action): JsonResponse
    {
        return $this->move($request, $account, $action, OperationType::Fund);
    }

    public function cashOut(MoveMoneyRequest $request, ServiceAccount $account, MoveAccountMoneyAction $action): JsonResponse
    {
        return $this->move($request, $account, $action, OperationType::CashOut);
    }

    private function move(MoveMoneyRequest $request, ServiceAccount $account, MoveAccountMoneyAction $action, OperationType $type): JsonResponse
    {
        $transaction = $action->handle(
            $this->branch->idOrFail(),
            $account->id,
            $type,
            (int) $request->validated('amount'),
            $request->validated('paid') !== null ? (int) $request->validated('paid') : null,
            MoneySource::from((string) $request->validated('source')),
            $request->validated('note'),
        );

        return (new ServiceTransactionResource($transaction->load('account')))->response()->setStatusCode(201);
    }
}
