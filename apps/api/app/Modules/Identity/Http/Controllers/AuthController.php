<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\RegisterTenantAction;
use App\Modules\Identity\BranchAccess;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterTenantRequest;
use App\Modules\Identity\Http\Resources\BranchResource;
use App\Modules\Identity\Http\Resources\TenantResource;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PermissionResolver;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController
{
    public function register(RegisterTenantRequest $request, RegisterTenantAction $action): JsonResponse
    {
        $owner = $action->handle($request->toData());

        return response()->json([
            'token' => $owner->createToken('web')->plainTextToken,
            'user' => new UserResource($owner),
        ], Response::HTTP_CREATED);
    }

    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('phone', $request->normalizedPhone())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['phone' => 'رقم الموبايل أو كلمة السر غير صحيحة.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['phone' => 'الحساب ده اتوقف. كلّم صاحب المحل.']);
        }

        return response()->json([
            'token' => $user->createToken($request->string('device_name')->toString())->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Everything a client needs to boot: who am I, which shop, which branches, modules, permissions and menu.
     */
    public function me(Request $request, ModuleRegistry $registry, ModuleAccess $access, PermissionResolver $permissions, BranchAccess $branches): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();
        $granted = $permissions->permissionsFor($user);

        $modules = [];
        $menu = [];

        foreach ($registry->all() as $module) {
            $state = $access->state($module->key);
            $usable = $access->enabled($module->key);

            if ($module->isOptional()) {
                $modules[] = ['key' => $module->key, 'state' => $state->value, 'usable' => $usable, 'available' => $module->available];
            }

            if (! $usable) {
                continue;
            }

            foreach ($module->menu as $item) {
                if ($item->ready && ($item->permission === null || in_array($item->permission, $granted, true))) {
                    $menu[] = [...$item->toArray(), 'module' => $module->key];
                }
            }
        }

        $branchIds = $branches->branchIdsFor($user);

        return response()->json([
            'data' => [
                'user' => new UserResource($user->load('role')),
                'tenant' => new TenantResource($user->tenant),
                'branches' => BranchResource::collection(
                    Branch::query()->whereIn('id', $branchIds)->orderByDesc('is_main')->orderBy('created_at')->get(),
                ),
                'current_branch_id' => $branches->resolve($user, $request->header('X-Branch-Id')),
                'enabled_modules' => $access->enabledKeys(),
                'modules' => $modules,
                'permissions' => $granted,
                'menu' => $menu,
            ],
        ]);
    }

    public function logout(Request $request): Response
    {
        $request->user()?->currentAccessToken()?->delete();

        return response()->noContent();
    }
}
