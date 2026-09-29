<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Auth\TwoFactor;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * The signed-in user's own two-factor sign-in (any user; see /settings/security).
 */
final class TwoFactorController
{
    public function __construct(private readonly TwoFactor $twoFactor) {}

    public function show(Request $request): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        return response()->json(['data' => [
            'enabled' => $user->hasTwoFactor(),
            'confirmed_at' => $user->two_factor_confirmed_at?->toIso8601String(),
            'recovery_codes_left' => $user->hasTwoFactor() ? $this->twoFactor->remainingRecoveryCodes($user) : 0,
        ]]);
    }

    /** Step 1: password → a new secret and its otpauth:// URL (for the QR code). */
    public function setup(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);

        return response()->json(['data' => $this->twoFactor->begin($this->user($request), $data['password'])]);
    }

    /** Step 2: a code from the app → on, with the one-time recovery codes. */
    public function confirm(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:16']]);

        return response()->json(['data' => ['recovery_codes' => $this->twoFactor->confirm($this->user($request), $data['code'])]]);
    }

    public function recoveryCodes(Request $request): JsonResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);

        return response()->json(['data' => ['recovery_codes' => $this->twoFactor->regenerateRecoveryCodes($this->user($request), $data['password'])]]);
    }

    public function destroy(Request $request): Response
    {
        $data = $request->validate([
            'password' => ['required', 'string'],
            'code' => ['required', 'string', 'max:32'],
        ]);

        $this->twoFactor->disable($this->user($request), $data['password'], $data['code']);

        return response()->noContent();
    }

    private function user(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
