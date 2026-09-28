<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Http\Controllers;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\ShopOrders\Actions\PlaceOrderAction;
use App\Modules\ShopOrders\Actions\TransitionOrderAction;
use App\Modules\ShopOrders\Enums\OrderStatus;
use App\Modules\ShopOrders\Enums\OrderType;
use App\Modules\ShopOrders\Http\Requests\PlaceOrderRequest;
use App\Modules\ShopOrders\Http\Requests\TransitionOrderRequest;
use App\Modules\ShopOrders\Http\Resources\ShopOrderResource;
use App\Modules\ShopOrders\Models\ShopOrder;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class ShopOrderController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly ShopDirectory $directory,
    ) {}

    /**
     * ?box=incoming (orders other shops placed with me) | outgoing (orders I placed). &open=1 hides finished ones.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $tenantId = $this->tenant->idOrFail();
        $box = $request->query('box') === 'outgoing' ? 'outgoing' : 'incoming';

        $orders = ShopOrder::query()
            ->where($box === 'incoming' ? 'seller_tenant_id' : 'buyer_tenant_id', $tenantId)
            ->when($request->boolean('open'), fn ($q) => $q->whereNotIn('status', [OrderStatus::Completed, OrderStatus::Rejected, OrderStatus::Cancelled]))
            ->with('items')
            ->latest()
            ->cursorPaginate(30);

        $shops = $this->directory->findMany($orders->getCollection()->map(fn (ShopOrder $o): string => $o->counterpartyOf($tenantId))->all());

        $orders->setCollection($orders->getCollection()->map(fn (ShopOrder $o): array => [
            'order' => $o,
            'tenantId' => $tenantId,
            'counterparty' => $shops[$o->counterpartyOf($tenantId)] ?? null,
        ]));

        return ShopOrderResource::collection($orders);
    }

    public function store(PlaceOrderRequest $request, PlaceOrderAction $action): JsonResponse
    {
        $order = $action->handle(
            buyerTenantId: $this->tenant->idOrFail(),
            userId: (string) $request->user()?->getAuthIdentifier(),
            sellerTenantId: $request->string('seller_tenant_id')->toString(),
            type: $request->enum('type', OrderType::class) ?? OrderType::Goods,
            items: $request->items(),
            neededBy: $request->validated('needed_by'),
            notes: $request->validated('notes'),
        );

        return $this->show($order->id)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function show(string $order): ShopOrderResource
    {
        $tenantId = $this->tenant->idOrFail();
        $model = ShopOrder::query()->with(['items', 'activities'])->findOrFail($order);

        return new ShopOrderResource([
            'order' => $model,
            'tenantId' => $tenantId,
            'counterparty' => $this->directory->find($model->counterpartyOf($tenantId)),
        ]);
    }

    public function transition(TransitionOrderRequest $request, string $order, TransitionOrderAction $action): ShopOrderResource
    {
        $action->handle(
            tenantId: $this->tenant->idOrFail(),
            userId: (string) $request->user()?->getAuthIdentifier(),
            orderId: $order,
            to: $request->enum('status', OrderStatus::class) ?? OrderStatus::Placed,
            note: $request->validated('note'),
            unitPrices: $request->prices(),
        );

        return $this->show($order);
    }
}
