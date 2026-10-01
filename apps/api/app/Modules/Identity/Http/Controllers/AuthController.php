<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Actions\RegisterTenantAction;
use App\Modules\Identity\Auth\DeviceSessions;
use App\Modules\Identity\Auth\LoginChallenges;
use App\Modules\Identity\Auth\TwoFactor;
use App\Modules\Identity\BranchAccess;
use App\Modules\Identity\Http\Requests\LoginRequest;
use App\Modules\Identity\Http\Requests\RegisterTenantRequest;
use App\Modules\Identity\Http\Resources\BranchResource;
use App\Modules\Identity\Http\Resources\TenantResource;
use App\Modules\Identity\Http\Resources\UserResource;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\User;
use App\Modules\Identity\PermissionResolver;
use App\Support\Modules\FeatureAccess;
use App\Support\Modules\ModuleAccess;
use App\Support\Modules\ModuleRegistry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

final class AuthController
{
    public function register(RegisterTenantRequest $request, RegisterTenantAction $action, DeviceSessions $sessions): JsonResponse
    {
        $owner = $action->handle($request->toData());

        return response()->json([
            'token' => $sessions->issue($owner, $request->string('device_name', 'web')->toString() ?: 'web'),
            'user' => new UserResource($owner),
        ], Response::HTTP_CREATED);
    }

    /**
     * Phone + password. With two-factor sign-in on, no token yet: a short-lived `challenge`
     * to send with the authenticator (or recovery) code to POST /auth/two-factor.
     */
    public function login(LoginRequest $request, DeviceSessions $sessions, LoginChallenges $challenges): JsonResponse
    {
        $user = User::query()->where('phone', $request->normalizedPhone())->first();

        if ($user === null || ! Hash::check($request->string('password')->toString(), $user->password)) {
            throw ValidationException::withMessages(['phone' => 'رقم الموبايل أو كلمة السر غير صحيحة.']);
        }

        if (! $user->is_active) {
            throw ValidationException::withMessages(['phone' => 'الحساب ده اتوقف. كلّم صاحب المحل.']);
        }

        $deviceName = $request->string('device_name')->toString();

        if ($user->hasTwoFactor()) {
            return response()->json([
                'two_factor' => true,
                'challenge' => $challenges->issue($user->id, $deviceName),
                'expires_in' => LoginChallenges::TTL_SECONDS,
            ]);
        }

        return response()->json([
            'token' => $sessions->issue($user, $deviceName),
            'user' => new UserResource($user),
        ]);
    }

    /** Second step of a two-factor sign-in: the challenge from /auth/login + a code. */
    public function twoFactor(Request $request, LoginChallenges $challenges, TwoFactor $twoFactor, DeviceSessions $sessions): JsonResponse
    {
        $data = $request->validate([
            'challenge' => ['required', 'string', 'max:128'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $challenge = $challenges->find($data['challenge']);
        $user = $challenge ? User::query()->find($challenge['user_id']) : null;

        if ($challenge === null || $user === null || ! $user->is_active || ! $user->hasTwoFactor()) {
            throw ValidationException::withMessages(['challenge' => 'انتهى وقت تسجيل الدخول. ادخل برقمك وكلمة السر تاني.']);
        }

        // Audit entries written while checking the code belong to this user.
        Auth::guard('sanctum')->setUser($user);

        $method = DB::transaction(fn (): ?string => $twoFactor->consume($twoFactor->locked($user), $data['code']));

        if ($method === null) {
            $left = $challenges->fail($data['challenge']);

            throw ValidationException::withMessages(['code' => $left > 0
                ? "الكود غلط. فاضل لك {$left} محاولات."
                : 'الكود غلط كذا مرة. ادخل برقمك وكلمة السر تاني.']);
        }

        $challenges->forget($data['challenge']);

        return response()->json([
            'token' => $sessions->issue($user, $challenge['device_name']),
            'user' => new UserResource($user->refresh()),
            'recovery_codes_left' => $method === 'recovery' ? $twoFactor->remainingRecoveryCodes($user) : null,
        ]);
    }

    /**
     * Everything a client needs to boot: who am I, which shop, which branches, modules, permissions and menu.
     */
    public function me(Request $request, ModuleRegistry $registry, ModuleAccess $access, PermissionResolver $permissions, BranchAccess $branches, FeatureAccess $features): JsonResponse
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
                if ($item->ready
                    && ($item->permission === null || in_array($item->permission, $granted, true))
                    && ($item->feature === null || $features->enabled($item->feature))) {
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
                // The owner's switches: what the screens should offer.
                'features' => $features->all(),
                // The values set next to some switches (e.g. the return window in days).
                'feature_settings' => $features->settings(),
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
