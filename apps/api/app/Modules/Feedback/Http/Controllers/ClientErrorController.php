<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Http\Controllers;

use App\Modules\Feedback\Models\ClientError;
use App\Modules\Feedback\Support\Scrub;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Uncaught errors from the shop app. The browser already groups and limits what it sends; here each
 * one is scrubbed, fingerprinted by kind + message and counted on one row per shop.
 */
final class ClientErrorController
{
    /** Different errors kept per shop; past that only the known ones are counted. */
    public const MAX_PER_SHOP = 200;

    public function store(Request $request, CurrentTenant $tenant): JsonResponse
    {
        $data = $request->validate([
            'app_version' => ['nullable', 'string', 'max:60'],
            'errors' => ['required', 'array', 'min:1', 'max:10'],
            'errors.*.kind' => ['required', 'in:error,rejection'],
            'errors.*.message' => ['required', 'string', 'max:2000'],
            'errors.*.source' => ['nullable', 'string', 'max:1000'],
            'errors.*.stack' => ['nullable', 'string', 'max:10000'],
            'errors.*.page' => ['nullable', 'string', 'max:500'],
            'errors.*.count' => ['nullable', 'integer', 'min:1', 'max:1000'],
        ]);
        $tenantId = $tenant->idOrFail();
        $userId = $request->user()?->getAuthIdentifier();
        $userAgent = mb_substr((string) $request->userAgent(), 0, 500) ?: null;
        $known = ClientError::query()->count();
        $accepted = 0;

        foreach ($data['errors'] as $error) {
            $message = Scrub::text($error['message'], 500) ?? '(no message)';
            $fingerprint = sha1($error['kind'].'|'.$message);
            $exists = ClientError::query()->where('fingerprint', $fingerprint)->exists();
            if (! $exists && $known >= self::MAX_PER_SHOP) {
                continue;
            }
            $now = now();
            DB::statement(
                'insert into client_errors (id, tenant_id, fingerprint, kind, message, source, stack, page, app_version, user_agent, last_user_id, count, first_seen_at, last_seen_at)
                 values (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
                 on conflict (tenant_id, fingerprint) do update set
                   count = least(client_errors.count + excluded.count, 2000000000), last_seen_at = excluded.last_seen_at,
                   source = excluded.source, stack = excluded.stack, page = excluded.page, app_version = excluded.app_version,
                   user_agent = excluded.user_agent, last_user_id = excluded.last_user_id, resolved_at = null',
                [
                    (string) Str::uuid7(), $tenantId, $fingerprint, $error['kind'], $message,
                    Scrub::source($error['source'] ?? null), Scrub::text($error['stack'] ?? null, 4000), Scrub::path($error['page'] ?? null),
                    $data['app_version'] ?? null, $userAgent, $userId, (int) ($error['count'] ?? 1), $now, $now,
                ],
            );
            $known += $exists ? 0 : 1;
            $accepted++;
        }

        return response()->json(['data' => ['accepted' => $accepted]], Response::HTTP_ACCEPTED);
    }
}
