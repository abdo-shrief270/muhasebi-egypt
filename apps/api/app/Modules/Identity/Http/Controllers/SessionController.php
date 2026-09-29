<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Auth\DeviceSessions;
use App\Modules\Identity\Auth\TwoFactor;
use App\Modules\Identity\Http\Resources\DeviceSessionResource;
use App\Modules\Identity\Models\User;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Signed-in devices: the user's own (account/sessions) and, for the owner, an employee's (users/{user}/sessions).
 */
final class SessionController
{
    public function __construct(private readonly DeviceSessions $sessions) {}

    public function index(Request $request): JsonResponse
    {
        $user = $this->me($request);

        return $this->list($user, $this->sessions->currentId($user));
    }

    public function destroy(Request $request, int $session): Response
    {
        $this->sessions->revoke($this->me($request), $session);

        return response()->noContent();
    }

    /** Log out everywhere else (keeps this device). */
    public function destroyOthers(Request $request): JsonResponse
    {
        $user = $this->me($request);

        return response()->json(['data' => ['revoked' => $this->sessions->revokeAll($user, $this->sessions->currentId($user))]]);
    }

    public function staffIndex(User $user): JsonResponse
    {
        return $this->list($user, null);
    }

    public function staffDestroy(User $user, int $session): Response
    {
        $this->sessions->revoke($user, $session, byOwner: true);

        return response()->noContent();
    }

    public function staffDestroyAll(Request $request, User $user): JsonResponse
    {
        // The owner signing themselves out everywhere from this page keeps the device they're on.
        $keep = $user->is($request->user()) ? $this->sessions->currentId($this->me($request)) : null;

        return response()->json(['data' => ['revoked' => $this->sessions->revokeAll($user, $keep, byOwner: true)]]);
    }

    /** The owner turns off two-factor sign-in for an employee who lost their phone. */
    public function staffResetTwoFactor(User $user, TwoFactor $twoFactor): Response
    {
        $twoFactor->reset($user);

        return response()->noContent();
    }

    private function list(User $user, ?int $currentId): JsonResponse
    {
        return response()->json([
            'data' => $this->sessions->of($user)->map(function ($token) use ($currentId) {
                $resource = new DeviceSessionResource($token);
                $resource->currentId = $currentId;

                return $resource;
            }),
        ]);
    }

    private function me(Request $request): User
    {
        /** @var User */
        return $request->user();
    }
}
