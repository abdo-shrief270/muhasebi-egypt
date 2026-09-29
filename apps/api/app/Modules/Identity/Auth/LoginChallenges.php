<?php

declare(strict_types=1);

namespace App\Modules\Identity\Auth;

use Illuminate\Contracts\Cache\Repository as Cache;
use Illuminate\Support\Str;

/**
 * The step between "password OK" and "token issued" for users with two-factor sign-in:
 * a random, short-lived challenge (only its hash is a cache key) that allows a few code attempts.
 */
final class LoginChallenges
{
    public const TTL_SECONDS = 300;

    public const MAX_ATTEMPTS = 5;

    public function __construct(private readonly Cache $cache) {}

    public function issue(string $userId, string $deviceName): string
    {
        $token = Str::random(64);
        $this->cache->put($this->key($token), ['user_id' => $userId, 'device_name' => $deviceName, 'attempts' => 0], self::TTL_SECONDS);

        return $token;
    }

    /**
     * @return array{user_id: string, device_name: string, attempts: int}|null
     */
    public function find(string $token): ?array
    {
        $challenge = $this->cache->get($this->key($token));

        return is_array($challenge) ? $challenge : null;
    }

    /** Counts a wrong code; returns how many tries are left (0 = the challenge is gone). */
    public function fail(string $token): int
    {
        $challenge = $this->find($token);
        if ($challenge === null) {
            return 0;
        }

        $challenge['attempts']++;
        $left = self::MAX_ATTEMPTS - $challenge['attempts'];

        if ($left <= 0) {
            $this->forget($token);

            return 0;
        }

        $this->cache->put($this->key($token), $challenge, self::TTL_SECONDS);

        return $left;
    }

    public function forget(string $token): void
    {
        $this->cache->forget($this->key($token));
    }

    private function key(string $token): string
    {
        return 'login-challenge:'.hash('sha256', $token);
    }
}
