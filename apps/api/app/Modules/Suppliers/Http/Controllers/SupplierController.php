<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Http\Controllers;

use App\Modules\Suppliers\Actions\RecordSupplierPaymentAction;
use App\Modules\Suppliers\Actions\SaveSupplierAction;
use App\Modules\Suppliers\Enums\PaymentMethod;
use App\Modules\Suppliers\Http\Requests\SaveSupplierRequest;
use App\Modules\Suppliers\Http\Requests\SupplierPaymentRequest;
use App\Modules\Suppliers\Http\Resources\SupplierResource;
use App\Modules\Suppliers\Http\Resources\SupplierTransactionResource;
use App\Modules\Suppliers\Models\Purchase;
use App\Modules\Suppliers\Models\Supplier;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class SupplierController
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    /** ?q= name or phone; &active=1 for pickers. Owed suppliers first. */
    public function index(Request $request): JsonResponse
    {
        $suppliers = Supplier::query()
            ->withCount('purchases')
            ->addSelect(['last_purchase_at' => Purchase::query()->selectRaw('max(invoice_date)')->whereColumn('supplier_id', 'suppliers.id')])
            ->when($request->filled('q'), fn ($query) => $query->where(fn ($w) => $w
                ->whereRaw('lower(name) like ?', ['%'.mb_strtolower((string) $request->query('q')).'%'])
                ->orWhere('phone', 'like', '%'.$request->query('q').'%')))
            ->when($request->boolean('active'), fn ($query) => $query->where('is_active', true))
            ->orderByDesc('is_active')
            ->orderByDesc('balance')
            ->orderBy('name')
            ->get();

        return response()->json([
            'data' => SupplierResource::collection($suppliers),
            'meta' => ['total_owed' => (int) $suppliers->where('balance', '>', 0)->sum('balance')],
        ]);
    }

    public function show(Supplier $supplier): SupplierResource
    {
        return new SupplierResource($supplier->loadCount('purchases'));
    }

    public function store(SaveSupplierRequest $request, SaveSupplierAction $action): JsonResponse
    {
        $supplier = $action->handle($this->tenant->idOrFail(), $request->supplierData(), openingBalance: (int) $request->validated('opening_balance', 0));

        return (new SupplierResource($supplier))->response()->setStatusCode(201);
    }

    public function update(SaveSupplierRequest $request, Supplier $supplier, SaveSupplierAction $action): SupplierResource
    {
        return new SupplierResource($action->handle($this->tenant->idOrFail(), $request->supplierData(), $supplier));
    }

    /** The account statement, newest first. */
    public function statement(Supplier $supplier): AnonymousResourceCollection
    {
        return SupplierTransactionResource::collection(
            $supplier->transactions()->orderByDesc('seq')->paginate(50),
        );
    }

    public function pay(SupplierPaymentRequest $request, Supplier $supplier, RecordSupplierPaymentAction $action, CurrentBranch $branch): JsonResponse
    {
        $transaction = $action->handle(
            $this->tenant->idOrFail(),
            $branch->idOrFail(),
            $supplier,
            (int) $request->validated('amount'),
            $request->enum('payment_method', PaymentMethod::class) ?? PaymentMethod::Cash,
            $request->validated('note'),
            $request->boolean('from_drawer', true),
        );

        return (new SupplierTransactionResource($transaction))->response()->setStatusCode(201);
    }

    public function paymentMethods(): JsonResponse
    {
        return response()->json(['data' => array_map(
            fn (PaymentMethod $m): array => ['value' => $m->value, 'label' => $m->label()],
            PaymentMethod::cases(),
        )]);
    }
}
