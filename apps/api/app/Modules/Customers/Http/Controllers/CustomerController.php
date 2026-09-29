<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Actions\RecordCustomerPaymentAction;
use App\Modules\Customers\Actions\SaveCustomerAction;
use App\Modules\Customers\Enums\PaymentMethod;
use App\Modules\Customers\Http\Requests\CustomerPaymentRequest;
use App\Modules\Customers\Http\Requests\SaveCustomerRequest;
use App\Modules\Customers\Http\Resources\CustomerResource;
use App\Modules\Customers\Http\Resources\CustomerTransactionResource;
use App\Modules\Customers\Models\Customer;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class CustomerController
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    /** ?q= name or phone; &owing=1 only those with a balance; &active=1 for pickers. */
    public function index(Request $request): JsonResponse
    {
        $customers = Customer::query()
            ->when($request->filled('q'), fn ($q) => $q->search((string) $request->query('q')))
            ->when($request->boolean('owing'), fn ($q) => $q->where('balance', '>', 0))
            ->when($request->boolean('active'), fn ($q) => $q->where('is_active', true))
            ->when($request->boolean('owing'), fn ($q) => $q->orderByDesc('balance'))
            ->orderByRaw('last_activity_at desc nulls last')
            ->orderBy('name')
            ->paginate(min(100, max(5, $request->integer('per_page', 30))));

        $owing = Customer::query()->where('balance', '>', 0);

        return response()->json([
            'data' => CustomerResource::collection($customers->getCollection()),
            'meta' => [
                'current_page' => $customers->currentPage(),
                'last_page' => $customers->lastPage(),
                'total' => $customers->total(),
                'receivable' => (int) (clone $owing)->sum('balance'),
                'owing_count' => (clone $owing)->count(),
            ],
        ]);
    }

    public function show(Customer $customer): CustomerResource
    {
        return new CustomerResource($customer);
    }

    public function store(SaveCustomerRequest $request, SaveCustomerAction $action): JsonResponse
    {
        $customer = $action->handle(
            $this->tenant->idOrFail(),
            $request->customerData(),
            openingBalance: (int) $request->validated('opening_balance', 0),
            consent: $request->consent(),
        );

        return (new CustomerResource($customer))->response()->setStatusCode(201);
    }

    public function update(SaveCustomerRequest $request, Customer $customer, SaveCustomerAction $action): CustomerResource
    {
        return new CustomerResource($action->handle($this->tenant->idOrFail(), $request->customerData(), $customer, consent: $request->consent()));
    }

    /** The account statement, newest first. */
    public function statement(Customer $customer): AnonymousResourceCollection
    {
        return CustomerTransactionResource::collection($customer->transactions()->orderByDesc('seq')->paginate(50));
    }

    public function pay(CustomerPaymentRequest $request, Customer $customer, RecordCustomerPaymentAction $action, CurrentBranch $branch): JsonResponse
    {
        $transaction = $action->handle(
            $this->tenant->idOrFail(),
            $branch->idOrFail(),
            $customer,
            (int) $request->validated('amount'),
            $request->enum('payment_method', PaymentMethod::class) ?? PaymentMethod::Cash,
            $request->validated('note'),
        );

        return (new CustomerTransactionResource($transaction))->response()->setStatusCode(201);
    }
}
