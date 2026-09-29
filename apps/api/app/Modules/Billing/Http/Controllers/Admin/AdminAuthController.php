<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Models\PlatformAdmin;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Symfony\Component\HttpFoundation\Response;

/** Sign-in for platform admins (a separate account from any shop). */
final class AdminAuthController
{
    public function login(Request $request): JsonResponse
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);
        $admin = PlatformAdmin::query()->where('email', mb_strtolower($data['email']))->first();

        if ($admin === null || ! $admin->is_active || ! Hash::check($data['password'], $admin->getAuthPassword())) {
            throw new DomainRuleException('الإيميل أو كلمة السر غلط.', 'invalid_credentials', Response::HTTP_UNPROCESSABLE_ENTITY);
        }
        $admin->update(['last_login_at' => now()]);

        return response()->json(['token' => $admin->createToken('admin', ['admin'])->plainTextToken, 'admin' => $this->present($admin)]);
    }

    public function me(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('platform-admin'), 403);

        return response()->json(['data' => $this->present($request->user())]);
    }

    public function logout(Request $request): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('platform-admin'), 403);
        $request->user()->currentAccessToken()?->delete();

        return response()->json(['data' => null]);
    }

    /** @return array<string, mixed> */
    private function present(PlatformAdmin $admin): array
    {
        return ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email];
    }
}
