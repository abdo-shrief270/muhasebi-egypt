<?php

declare(strict_types=1);

namespace App\Modules\ShopOrders\Http\Controllers;

use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\ShopOrders\Actions\RequestConnectionAction;
use App\Modules\ShopOrders\Actions\RespondToConnectionAction;
use App\Modules\ShopOrders\Http\Requests\RequestConnectionRequest;
use App\Modules\ShopOrders\Http\Resources\ConnectionResource;
use App\Modules\ShopOrders\Models\ShopConnection;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class ConnectionController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly ShopDirectory $directory,
    ) {}

    public function index(): AnonymousResourceCollection
    {
        $tenantId = $this->tenant->idOrFail();
        $connections = ShopConnection::query()->latest()->limit(200)->get();
        $shops = $this->directory->findMany($connections->map(fn (ShopConnection $c): string => $c->otherParty($tenantId))->all());

        return ConnectionResource::collection($connections->map(fn (ShopConnection $c): array => [
            'connection' => $c,
            'tenantId' => $tenantId,
            'shop' => $shops[$c->otherParty($tenantId)] ?? null,
        ]));
    }

    public function store(RequestConnectionRequest $request, RequestConnectionAction $action): JsonResponse
    {
        $connection = $action->handle($this->tenant->idOrFail(), (string) $request->user()?->getAuthIdentifier(), $request->string('code')->toString());

        return $this->resource($connection)->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function accept(Request $request, string $connection, RespondToConnectionAction $action): ConnectionResource
    {
        return $this->resource($action->handle($this->tenant->idOrFail(), $connection, accept: true));
    }

    public function decline(Request $request, string $connection, RespondToConnectionAction $action): ConnectionResource
    {
        return $this->resource($action->handle($this->tenant->idOrFail(), $connection, accept: false));
    }

    private function resource(ShopConnection $connection): ConnectionResource
    {
        $tenantId = $this->tenant->idOrFail();

        return new ConnectionResource([
            'connection' => $connection,
            'tenantId' => $tenantId,
            'shop' => $this->directory->find($connection->otherParty($tenantId)),
        ]);
    }
}
