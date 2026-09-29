<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\SaveBranchAction;
use App\Modules\Identity\Http\Requests\SaveBranchRequest;
use App\Modules\Identity\Http\Resources\BranchResource;
use App\Modules\Identity\Models\Branch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

final class BranchController
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    /** Every branch, for the screens that manage branches or staff (others get theirs from /auth/me). */
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()?->can('branches.manage') || $request->user()?->can('users.manage'), 403);

        return BranchResource::collection(Branch::query()->orderByDesc('is_main')->orderBy('created_at')->get());
    }

    public function store(SaveBranchRequest $request, SaveBranchAction $action): JsonResponse
    {
        return (new BranchResource($action->handle($this->tenant->idOrFail(), $request->validated())))
            ->response()->setStatusCode(Response::HTTP_CREATED);
    }

    public function update(SaveBranchRequest $request, Branch $branch, SaveBranchAction $action): BranchResource
    {
        return new BranchResource($action->handle($this->tenant->idOrFail(), $request->validated(), $branch));
    }
}
