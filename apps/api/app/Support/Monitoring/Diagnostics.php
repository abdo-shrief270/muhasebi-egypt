<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints that misbehave on purpose, to test an uptime monitor / alerting from outside:
 * a 5xx, a slow answer, and no answer at all. Off unless DIAGNOSTICS_TOKEN is set, and then only
 * with that token (`?token=` or `X-Diagnostics-Token`); otherwise they're a plain 404. They never
 * throw, so they don't reach server_errors or the Telegram alerts.
 */
final class Diagnostics
{
    /** `?status=` 500–599 (default 500). */
    public function error(Request $request): JsonResponse
    {
        $this->guard($request);
        $status = (int) $request->query('status', '500');

        return response()->json(
            ['message' => 'خطأ تجريبي (diagnostics).', 'code' => 'diagnostics_error'],
            $status >= 500 && $status <= 599 ? $status : 500,
        )->header('Cache-Control', 'no-store');
    }

    /** Answers 200 after `?seconds=` (default 6, at most 60). */
    public function slow(Request $request): JsonResponse
    {
        $this->guard($request);
        $seconds = min(60.0, max(0.0, (float) $request->query('seconds', '6')));
        set_time_limit((int) ceil($seconds) + 30);
        usleep((int) ($seconds * 1_000_000));

        return response()->json(['ok' => true, 'slept_seconds' => $seconds])->header('Cache-Control', 'no-store');
    }

    /**
     * Sends nothing for `?seconds=` (default DIAGNOSTICS_HANG_SECONDS, at most 600): longer than a
     * monitor waits, so to it the server never answers. (Behind Caddy a dropped connection would
     * still come back as a 502, so holding the request is how «no answer» looks from outside.)
     * Whoever is still waiting after that gets a 504.
     */
    public function hang(Request $request): JsonResponse
    {
        $this->guard($request);
        $seconds = min(600, max(1, (int) $request->query('seconds', (string) config('services.diagnostics.hang_seconds'))));
        set_time_limit($seconds + 30);
        sleep($seconds);

        return response()->json(['message' => 'انتهى وقت الانتظار التجريبي.', 'code' => 'diagnostics_hang'], 504)->header('Cache-Control', 'no-store');
    }

    private function guard(Request $request): void
    {
        $token = (string) config('services.diagnostics.token');
        $given = (string) ($request->header('X-Diagnostics-Token') ?? $request->query('token', ''));
        abort_if($token === '' || ! hash_equals($token, $given), 404);
    }
}
