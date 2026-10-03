<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth;

use App\Modules\Identity\Contracts\AccountSecurity;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Auth\Factory as Auth;
use Illuminate\Contracts\Cache\Repository as Cache;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * «قفل التطبيق»: each signed-in device (Sanctum token) proves it's still its user with a passkey
 * or the PIN. A success counts for MINUTES (a big approval asks again after that); MAX_FAILS wrong
 * tries in a row sign the device out.
 */
final class AppUnlock implements AccountSecurity
{
    public const MINUTES = 5;

    public const MAX_FAILS = 5;

    public function __construct(
        private readonly Auth $auth,
        private readonly Cache $cache,
    ) {}

    public function recentlyVerified(): bool
    {
        $token = $this->tokenId();

        return $token !== null && $this->cache->has("app-unlock:{$token}");
    }

    public function twoFactorEnabled(?string $userId = null): bool
    {
        $user = $userId === null
            ? $this->auth->guard('sanctum')->user()
            : User::query()->find($userId);

        return $user instanceof User && $user->hasTwoFactor();
    }

    /** @return string the time until which this device counts as verified (ISO 8601) */
    public function verified(): string
    {
        $token = $this->tokenId();
        $until = now()->addMinutes(self::MINUTES);
        $this->cache->forget("app-unlock-fails:{$token}");
        $this->cache->put("app-unlock:{$token}", true, $until);

        return $until->toIso8601String();
    }

    /** @return bool true when this was one wrong try too many (the caller signs the device out) */
    public function failed(): bool
    {
        $key = 'app-unlock-fails:'.$this->tokenId();
        $fails = (int) $this->cache->get($key, 0) + 1;
        $this->cache->put($key, $fails, now()->addHour());

        return $fails >= self::MAX_FAILS;
    }

    /** The signed-in device: its token, or the user when there is none (Sanctum::actingAs in tests). */
    public function tokenId(): ?string
    {
        $user = $this->auth->guard('sanctum')->user();
        $token = $user?->currentAccessToken();

        return match (true) {
            $token instanceof PersonalAccessToken => (string) $token->getKey(),
            $user !== null => 'user-'.$user->getAuthIdentifier(),
            default => null,
        };
    }
}
