<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\DeleteRoleAction;
use App\Modules\Identity\Actions\SaveRoleAction;
use App\Modules\Identity\Http\Requests\SaveRoleRequest;
use App\Modules\Identity\Http\Resources\RoleResource;
use App\Modules\Identity\Models\Role;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

final class RoleController
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function index(): AnonymousResourceCollection
    {
        return RoleResource::collection(Role::query()->withCount('users')->orderBy('id')->get());
    }

    public function store(SaveRoleRequest $request, SaveRoleAction $action): JsonResponse
    {
        $role = $action->handle($this->tenant->idOrFail(), $request->string('name')->toString(), $request->validated('permissions'));

        return (new RoleResource($role->loadCount('users')))->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveRoleRequest $request, Role $role, SaveRoleAction $action): RoleResource
    {
        return new RoleResource($action->handle($this->tenant->idOrFail(), $request->string('name')->toString(), $request->validated('permissions'), $role)->loadCount('users'));
    }

    public function destroy(Role $role, DeleteRoleAction $action): Response
    {
        $action->handle($role);

        return response()->noContent();
    }
}
