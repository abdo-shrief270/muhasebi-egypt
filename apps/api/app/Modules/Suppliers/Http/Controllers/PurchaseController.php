<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Controllers;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Catalog\Contracts\VariantSummary;
use App\Modules\Inventory\Contracts\StockLedger;
use App\Modules\Suppliers\Actions\CreatePurchaseAction;
use App\Modules\Suppliers\Actions\CreatePurchaseReturnAction;
use App\Modules\Suppliers\Enums\PaymentMethod;
use App\Modules\Suppliers\Http\Requests\CreatePurchaseRequest;
use App\Modules\Suppliers\Http\Requests\PurchaseReturnRequest;
use App\Modules\Suppliers\Http\Resources\PurchaseResource;
use App\Modules\Suppliers\Models\Purchase;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class PurchaseController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly CurrentBranch $branch,
        private readonly VariantCatalog $catalog,
    ) {}

    /** ?supplier_id= &q= (number or the supplier's invoice number) */
    public function index(Request $request): JsonResponse
    {
        $purchases = Purchase::query()
            ->with(['supplier:id,name', 'items:id,purchase_id'])
            ->when($request->filled('supplier_id'), fn ($q) => $q->where('supplier_id', (string) $request->query('supplier_id')))
            ->when($request->filled('q'), function ($q) use ($request): void {
                $term = trim((string) $request->query('q'));
                $number = (int) preg_replace('/\D/', '', $term);
                $q->where(fn ($w) => $w
                    ->where('supplier_invoice_no', 'ilike', "%{$term}%")
                    ->when($number > 0, fn ($x) => $x->orWhere('number', $number)));
            })
            ->orderByDesc('invoice_date')
            ->orderByDesc('number')
            ->paginate(30);

        return response()->json([
            'data' => $purchases->getCollection()->map(fn (Purchase $p) => new PurchaseResource(['purchase' => $p])),
            'meta' => ['current_page' => $purchases->currentPage(), 'last_page' => $purchases->lastPage(), 'per_page' => $purchases->perPage(), 'total' => $purchases->total()],
        ]);
    }

    public function show(string $purchase): PurchaseResource
    {
        $model = Purchase::query()->with(['supplier', 'items', 'returns'])->findOrFail($purchase);

        return new PurchaseResource(['purchase' => $model, 'variants' => $this->catalog->find($model->items->pluck('variant_id')->all())]);
    }

    public function store(CreatePurchaseRequest $request, CreatePurchaseAction $action): JsonResponse
    {
        $purchase = $action->handle(
            tenantId: $this->tenant->idOrFail(),
            branchId: $this->branch->idOrFail(),
            supplierId: (string) $request->validated('supplier_id'),
            items: $request->items(),
            invoiceDate: (string) $request->validated('invoice_date'),
            supplierInvoiceNo: $request->validated('supplier_invoice_no'),
            discount: (int) $request->validated('discount', 0),
            paid: (int) $request->validated('paid', 0),
            paymentMethod: $request->enum('payment_method', PaymentMethod::class),
            notes: $request->validated('notes'),
            fromDrawer: $request->boolean('from_drawer', true),
        );

        return $this->show($purchase->id)->response()->setStatusCode(201);
    }

    public function return(PurchaseReturnRequest $request, string $purchase, CreatePurchaseReturnAction $action): JsonResponse
    {
        $model = Purchase::query()->findOrFail($purchase);
        $action->handle($this->tenant->idOrFail(), $model, $request->lines(), $request->validated('notes'));

        return $this->show($model->id)->response()->setStatusCode(201);
    }

    /**
     * Variants to buy, with their current average cost in this branch as the default price.
     * An exact barcode match comes first, so a scanner adds the right item straight away.
     */
    public function variants(Request $request, StockLedger $stock): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));
        $page = $this->catalog->search($q === '' ? null : $q, null, null, 1, 20);
        $items = $page['items'];

        usort($items, fn (VariantSummary $a, VariantSummary $b): int => (int) ($b->barcode === $q) <=> (int) ($a->barcode === $q));
        $costs = $stock->averageCosts($this->branch->idOrFail(), array_map(fn (VariantSummary $v) => $v->id, $items));

        return response()->json(['data' => array_map(fn (VariantSummary $v): array => [
            ...$v->toArray(),
            'display_name' => $v->displayName(),
            'avg_cost' => $costs[$v->id] ?? null,
            'exact_barcode' => $q !== '' && $v->barcode === $q,
        ], $items)]);
    }
}
