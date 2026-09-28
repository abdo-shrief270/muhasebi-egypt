<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\SaveUserAction;
use App\Modules\Identity\Http\Requests\SaveUserRequest;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\User;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class UserController
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function index(): AnonymousResourceCollection
    {
        return UserResource::collection(
            User::query()
                ->where('tenant_id', $this->tenant->idOrFail())
                ->with(['role', 'branches'])
                ->orderByDesc('is_owner')->orderByDesc('is_active')->orderBy('name')
                ->get(),
        );
    }

    public function store(SaveUserRequest $request, SaveUserAction $action): JsonResponse
    {
        $user = $action->handle($this->tenant->idOrFail(), $request->validated());

        return (new UserResource($user->load(['role', 'branches'])))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveUserRequest $request, User $user, SaveUserAction $action): UserResource
    {
        return new UserResource($action->handle($this->tenant->idOrFail(), $request->validated(), $user)->load(['role', 'branches']));
    }
}
