<?php

declare(strict_types=1);

namespace App\Modules\Services\Http\Controllers;

use App\Modules\Services\Actions\RecordOperationAction;
use App\Modules\Services\Actions\ReverseTransactionAction;
use App\Modules\Services\Enums\OperationType;
use App\Modules\Services\Http\Requests\OperationRequest;
use App\Modules\Services\Http\Requests\ReverseRequest;
use App\Modules\Services\Http\Resources\ServiceTransactionResource;
use App\Modules\Services\Models\ServiceTransaction;
use App\Modules\Services\Support\DailyUsage;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\FeatureAccess;
use App\Support\Tenancy\CurrentBranch;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class ServiceTransactionController
{
    public function __construct(private readonly CurrentBranch $branch) {}

    /**
     * The branch's operations, newest first. Filters: account_id, type, from / to (Cairo dates),
     * q (number, customer phone digits or name, reference), mine=1.
     */
    public function index(Request $request): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $digits = preg_replace('/\D/', '', $q) ?? '';

        $page = ServiceTransaction::query()
            ->with(['account', 'reversed:id,number'])
            ->withExists('reversal')
            ->where('branch_id', $this->branch->idOrFail())
            ->when($request->filled('account_id'), fn ($w) => $w->where('account_id', (string) $request->query('account_id')))
            ->when(OperationType::tryFrom((string) $request->query('type')), fn ($w, OperationType $t) => $w->where('type', $t->value))
            ->when($this->date($request, 'from'), fn ($w, CarbonImmutable $d) => $w->where('created_at', '>=', $d->startOfDay()->utc()))
            ->when($this->date($request, 'to'), fn ($w, CarbonImmutable $d) => $w->where('created_at', '<=', $d->endOfDay()->utc()))
            ->when($request->boolean('mine'), fn ($w) => $w->where('user_id', $request->user()?->getAuthIdentifier()))
            ->when($q !== '', fn ($w) => $w->where(function ($s) use ($q, $digits): void {
                if ($digits !== '' && strlen($digits) <= 9 && ctype_digit(ltrim($q, '#'))) {
                    $s->orWhere('number', (int) $digits);
                }
                if (strlen($digits) >= 4) {
                    // Typed as 010… or +2010…: match the digits after the leading 0.
                    $s->orWhere('customer_phone', 'like', '%'.ltrim($digits, '0').'%');
                }
                $s->orWhereRaw('lower(customer_name) like ?', ['%'.mb_strtolower($q).'%'])
                    ->orWhere('reference', 'like', '%'.$q.'%');
            }))
            ->orderByDesc('seq')
            ->paginate(50);

        return response()->json([
            'data' => ServiceTransactionResource::collection($page->getCollection()),
            'meta' => ['current_page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'per_page' => $page->perPage(), 'total' => $page->total()],
        ]);
    }

    public function show(ServiceTransaction $transaction): ServiceTransactionResource
    {
        return new ServiceTransactionResource($transaction->load(['account', 'reversed:id,number'])->loadExists('reversal'));
    }

    public function store(OperationRequest $request, RecordOperationAction $action, FeatureAccess $features): JsonResponse
    {
        if ($request->validated('type') === OperationType::Topup->value) {
            $features->ensure('services.airtime');
        }
        if (trim((string) $request->validated('customer_phone')) === '' && $features->enabled('services.require_customer_phone')) {
            throw new DomainRuleException('اكتب رقم موبايل العميل الأول.', 'customer_phone_required');
        }
        [$transaction, $warning] = $action->handle(
            $this->branch->idOrFail(),
            (string) $request->validated('account_id'),
            OperationType::from((string) $request->validated('type')),
            (int) $request->validated('amount'),
            $request->validated('fee') !== null ? (int) $request->validated('fee') : null,
            (bool) $request->user()?->can('services.fees'),
            $request->safe()->only(['customer_name', 'customer_phone', 'reference', 'note']),
        );

        return (new ServiceTransactionResource($transaction->load('account')))
            ->additional(['meta' => ['warning' => $warning]])
            ->response()
            ->setStatusCode(201);
    }

    public function reverse(ReverseRequest $request, ServiceTransaction $transaction, ReverseTransactionAction $action): JsonResponse
    {
        $reversal = $action->handle($this->branch->idOrFail(), $transaction, (string) $request->validated('reason'));

        return (new ServiceTransactionResource($reversal->load(['account', 'reversed:id,number'])))->response()->setStatusCode(201);
    }

    private function date(Request $request, string $key): ?CarbonImmutable
    {
        $value = (string) $request->query($key, '');

        return preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) ? CarbonImmutable::parse($value, DailyUsage::TZ) : null;
    }
}
