<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Controllers;

use App\Modules\Sales\Actions\CompleteSaleAction;
use App\Modules\Sales\Actions\CreateSaleReturnAction;
use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\PriceLevel;
use App\Modules\Sales\Http\Requests\CompleteSaleRequest;
use App\Modules\Sales\Http\Requests\SaleReturnRequest;
use App\Modules\Sales\Http\Resources\SaleResource;
use App\Modules\Sales\Models\Sale;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class SaleController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly CurrentBranch $branch,
    ) {}

    /** Sales of the current branch (or of ?customer_id= anywhere). ?q= invoice number or customer &from=&to= (dates) */
    public function index(Request $request): JsonResponse
    {
        $sales = Sale::query()
            ->withCount('items')
            // A customer's sales come from every branch; otherwise this branch's.
            ->when(
                $request->filled('customer_id'),
                fn ($q) => $q->where('customer_id', (string) $request->query('customer_id')),
                fn ($q) => $q->where('branch_id', $this->branch->idOrFail()),
            )
            ->when($request->filled('q'), function ($query) use ($request): void {
                $term = trim((string) $request->query('q'));
                $number = (int) preg_replace('/\D/', '', $term);
                $query->where(fn ($w) => $w
                    ->where('customer_name', 'ilike', "%{$term}%")
                    ->orWhere('customer_phone', 'like', "%{$term}%")
                    ->when($number > 0, fn ($x) => $x->orWhere('number', $number)));
            })
            ->when($request->filled('from'), fn ($q) => $q->where('completed_at', '>=', $request->date('from', null, 'Africa/Cairo')?->startOfDay()))
            ->when($request->filled('to'), fn ($q) => $q->where('completed_at', '<=', $request->date('to', null, 'Africa/Cairo')?->endOfDay()))
            ->orderByDesc('completed_at')
            ->paginate(30);

        $withCost = (bool) $request->user()?->can('reports.profit');

        return response()->json([
            'data' => $sales->getCollection()->map(fn (Sale $s) => (new SaleResource($s))->withCost($withCost)),
            'meta' => ['current_page' => $sales->currentPage(), 'last_page' => $sales->lastPage(), 'per_page' => $sales->perPage(), 'total' => $sales->total()],
        ]);
    }

    public function show(Request $request, string $sale): SaleResource
    {
        return (new SaleResource(Sale::query()->with(['items.returnItems', 'payments', 'returns'])->findOrFail($sale)))
            ->withCost((bool) $request->user()?->can('reports.profit'));
    }

    public function store(CompleteSaleRequest $request, CompleteSaleAction $action): JsonResponse
    {
        $sale = $action->handle(
            tenantId: $this->tenant->idOrFail(),
            branchId: $this->branch->idOrFail(),
            saleId: $request->validated('id'),
            items: $request->items(),
            payments: $request->payments(),
            discount: (int) $request->validated('discount', 0),
            priceLevel: $request->enum('price_level', PriceLevel::class) ?? PriceLevel::Retail,
            canDiscount: (bool) $request->user()?->can('sales.discount'),
            customerName: $request->validated('customer_name'),
            customerPhone: $request->validated('customer_phone'),
            notes: $request->validated('notes'),
            customerId: $request->validated('customer_id'),
            canCredit: (bool) $request->user()?->can('customers.credit'),
        );

        return $this->show($request, $sale->id)->response()->setStatusCode(201);
    }

    public function return(SaleReturnRequest $request, string $sale, CreateSaleReturnAction $action): JsonResponse
    {
        $action->handle(
            $this->tenant->idOrFail(),
            $this->branch->idOrFail(),
            Sale::query()->findOrFail($sale),
            $request->lines(),
            $request->enum('refund_method', PaymentMethod::class) ?? PaymentMethod::Cash,
            $request->validated('reason'),
        );

        return $this->show($request, $sale)->response()->setStatusCode(201);
    }
}
