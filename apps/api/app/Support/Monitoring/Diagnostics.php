<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Endpoints that misbehave on purpose, to test an uptime monitor / alerting from outside:
 * a 5xx, a slow answer, and no answer at all. Off unless DIAGNOSTICS_TOKEN is set, and then only
 * with that token (`?token=`, a `token` body field or `X-Diagnostics-Token`); otherwise they're a plain 404.
 * Any HTTP method; `status` / `seconds` / `token` come from the query string or the body. They never
 * throw, so they don't reach server_errors or the Telegram alerts.
 */
final class Diagnostics
{
    /** `?status=` 500–599 (default 500). */
    public function error(Request $request): JsonResponse
    {
        $this->guard($request);
        $status = (int) $request->input('status', '500');

        return response()->json(
            ['message' => 'خطأ تجريبي (diagnostics).', 'code' => 'diagnostics_error'],
            $status >= 500 && $status <= 599 ? $status : 500,
        )->header('Cache-Control', 'no-store');
    }

    /** Answers 200 after `?seconds=` (default 6, at most 60). */
    public function slow(Request $request): JsonResponse
    {
        $this->guard($request);
        $seconds = min(60.0, max(0.0, (float) $request->input('seconds', '6')));
        self::allow((int) ceil($seconds));
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
        $seconds = min(600, max(1, (int) $request->input('seconds', (string) config('services.diagnostics.hang_seconds'))));
        self::allow($seconds);
        sleep($seconds);

        return response()->json(['message' => 'انتهى وقت الانتظار التجريبي.', 'code' => 'diagnostics_hang'], 504)->header('Cache-Control', 'no-store');
    }

    /** Lets the request run that long: only ever raises PHP's limit (none stays none). */
    private static function allow(int $seconds): void
    {
        $limit = (int) ini_get('max_execution_time');
        if ($limit > 0 && $limit < $seconds + 30) {
            set_time_limit($seconds + 30);
        }
    }

    private function guard(Request $request): void
    {
        $token = (string) config('services.diagnostics.token');
        $given = (string) ($request->header('X-Diagnostics-Token') ?? $request->input('token', ''));
        abort_if($token === '' || ! hash_equals($token, $given), 404);
    }
}
