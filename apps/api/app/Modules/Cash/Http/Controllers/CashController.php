<?php

declare(strict_types=1);

namespace App\Modules\Cash\Http\Controllers;

use App\Modules\Cash\Actions\CloseShiftAction;
use App\Modules\Cash\Actions\OpenShiftAction;
use App\Modules\Cash\Actions\RecordCashMovementAction;
use App\Modules\Cash\Contracts\ExpenseCategory;
use App\Modules\Cash\Enums\Method;
use App\Modules\Cash\Enums\MovementType;
use App\Modules\Cash\Http\Requests\CashMovementRequest;
use App\Modules\Cash\Http\Requests\CloseShiftRequest;
use App\Modules\Cash\Http\Requests\OpenShiftRequest;
use App\Modules\Cash\Http\Resources\CashMovementResource;
use App\Modules\Cash\Http\Resources\CashShiftResource;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashShift;
use App\Modules\Cash\Support\ShiftTotals;
use App\Support\Modules\FeatureAccess;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class CashController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly CurrentBranch $branch,
    ) {}

    /** The signed-in user's open shift in this branch, with its movements; null when none. */
    public function current(Request $request): JsonResponse
    {
        $shift = CashShift::query()
            ->where('branch_id', $this->branch->idOrFail())
            ->where('user_id', $request->user()?->getAuthIdentifier())
            ->whereNull('closed_at')
            ->first();

        return response()->json(['data' => $shift ? (new CashShiftResource($shift))->withDetail() : null]);
    }

    /** Shifts of this branch, newest first: everyone's with cash.manage, otherwise your own. */
    public function index(Request $request): JsonResponse
    {
        $shifts = CashShift::query()
            ->where('branch_id', $this->branch->idOrFail())
            ->unless($request->user()?->can('cash.manage'), fn ($q) => $q->where('user_id', $request->user()?->getAuthIdentifier()))
            ->orderByDesc('opened_at')
            ->paginate(30);

        return response()->json([
            'data' => CashShiftResource::collection($shifts->getCollection()),
            'meta' => ['current_page' => $shifts->currentPage(), 'last_page' => $shifts->lastPage(), 'total' => $shifts->total()],
        ]);
    }

    public function show(Request $request, CashShift $shift): CashShiftResource
    {
        abort_unless($request->user()?->can('cash.manage') || $shift->user_id === $request->user()?->getAuthIdentifier(), 403);

        return (new CashShiftResource($shift))->withDetail();
    }

    public function open(OpenShiftRequest $request, OpenShiftAction $action): JsonResponse
    {
        $shift = $action->handle(
            $this->tenant->idOrFail(),
            $this->branch->idOrFail(),
            $request->user() ?? abort(401),
            (int) $request->validated('opening_cash'),
            $request->validated('note'),
        );

        return (new CashShiftResource($shift))->withDetail()->response()->setStatusCode(201);
    }

    public function close(CloseShiftRequest $request, CashShift $shift, CloseShiftAction $action): CashShiftResource
    {
        return (new CashShiftResource($action->handle($shift, $request->user() ?? abort(401), $request->counted(), $request->validated('note'))))->withDetail();
    }

    public function movement(CashMovementRequest $request, RecordCashMovementAction $action, FeatureAccess $features): JsonResponse
    {
        $features->ensure($request->validated('type') === MovementType::Expense->value ? 'cash.expenses' : 'cash.deposits');
        $movement = $action->handle(
            $this->branch->idOrFail(),
            $request->user() ?? abort(401),
            MovementType::from((string) $request->validated('type')),
            (int) $request->validated('amount'),
            $request->enum('category', ExpenseCategory::class),
            $request->validated('note'),
        );

        return (new CashMovementResource($movement))->response()->setStatusCode(201);
    }

    /**
     * For the owner: cash in every open drawer (all branches) and today's expenses.
     */
    public function summary(): JsonResponse
    {
        $open = CashShift::query()->whereNull('closed_at')->orderBy('opened_at')->get();
        $today = CarbonImmutable::now('Africa/Cairo')->startOfDay()->utc();

        return response()->json(['data' => [
            'in_drawers' => $open->sum(fn (CashShift $s) => ShiftTotals::cash($s)),
            'open_shifts' => $open->map(fn (CashShift $s): array => [
                'id' => $s->id,
                'reference' => $s->reference(),
                'branch_id' => $s->branch_id,
                'user_name' => $s->user_name,
                'opened_at' => $s->opened_at->toIso8601String(),
                'cash' => ShiftTotals::cash($s),
            ])->values(),
            'expenses_today' => -(int) CashMovement::query()
                ->where('type', MovementType::Expense->value)
                ->where('created_at', '>=', $today)
                ->sum('amount'),
        ]]);
    }

    public function options(): JsonResponse
    {
        return response()->json(['data' => [
            'methods' => array_map(fn (Method $m): array => ['value' => $m->value, 'label' => $m->label()], Method::cases()),
            'expense_categories' => array_map(fn (ExpenseCategory $c): array => ['value' => $c->value, 'label' => $c->label()], ExpenseCategory::cases()),
        ]]);
    }
}
