<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\RegisterTenantAction;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterTenantRequest;
use App\Modules\Identity\Http\Resources\BranchResource;
use App\Modules\Identity\Http\Resources\TenantResource;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\User;
use App\Support\Modules\MenuItem;
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

        return response()->json([
            'token' => $user->createToken($request->string('device_name')->toString())->plainTextToken,
            'user' => new UserResource($user),
        ]);
    }

    /**
     * Everything a client needs to boot: who am I, which shop, which branches, which modules and menu.
     */
    public function me(Request $request, ModuleRegistry $registry, ModuleAccess $access): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        $modules = [];
        $menu = [];

        foreach ($registry->all() as $module) {
            $state = $access->state($module->key);

            if ($module->isOptional()) {
                $modules[] = ['key' => $module->key, 'state' => $state->value, 'usable' => $state->isUsable()];
            }

            if ($state->isUsable()) {
                array_push($menu, ...array_map(
                    fn (MenuItem $item): array => [...$item->toArray(), 'module' => $module->key],
                    $module->menu,
                ));
            }
        }

        return response()->json([
            'data' => [
                'user' => new UserResource($user),
                'tenant' => new TenantResource($user->tenant),
                'branches' => BranchResource::collection(Branch::query()->where('is_active', true)->orderByDesc('is_main')->get()),
                'enabled_modules' => $access->enabledKeys(),
                'modules' => $modules,
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
