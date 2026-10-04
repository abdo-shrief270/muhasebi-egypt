<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Support\AdminLog;
use App\Support\Monitoring\Alerts;
use App\Support\Monitoring\Health;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/** Platform admins: what broke on the server, and whether everything is running. */
final class AdminMonitoringController
{
    public function __construct(private readonly AdminLog $log) {}

    public function health(Health $health, Alerts $alerts): JsonResponse
    {
        return response()->json(['data' => [...$health->check(), 'alerts_configured' => $alerts->configured()]]);
    }

    public function errors(Request $request): JsonResponse
    {
        $data = $request->validate(['resolved' => ['nullable', 'boolean'], 'q' => ['nullable', 'string', 'max:100']]);
        $rows = DB::table('server_errors')
            ->when(! ($data['resolved'] ?? false), fn ($q) => $q->whereNull('resolved_at'))
            ->when(($data['q'] ?? '') !== '', fn ($q) => $q->where(fn ($w) => $w->where('message', 'ilike', '%'.$data['q'].'%')->orWhere('class', 'ilike', '%'.$data['q'].'%')))
            ->orderByDesc('last_seen_at')->limit(100)->get();

        return response()->json([
            'data' => $rows->map(fn (object $r) => [
                'id' => $r->id,
                'class' => $r->class,
                'message' => $r->message,
                'file' => $r->file,
                'trace' => $r->trace,
                'context' => $r->context,
                'last_tenant_id' => $r->last_tenant_id,
                'count' => (int) $r->count,
                'first_seen_at' => Carbon::parse($r->first_seen_at)->toIso8601String(),
                'last_seen_at' => Carbon::parse($r->last_seen_at)->toIso8601String(),
                'resolved' => $r->resolved_at !== null,
            ])->values(),
            'meta' => [
                'open' => DB::table('server_errors')->whereNull('resolved_at')->count(),
                'last_24h' => DB::table('server_errors')->where('last_seen_at', '>=', now()->subDay())->count(),
            ],
        ]);
    }

    public function resolve(Request $request, string $error): JsonResponse
    {
        $data = $request->validate(['resolved' => ['sometimes', 'boolean']]);
        $resolved = $data['resolved'] ?? true;
        $changed = DB::table('server_errors')->where('id', $error)->update(['resolved_at' => $resolved ? now() : null]);
        abort_if($changed === 0, 404);
        $this->log->record($resolved ? 'server_error_resolved' : 'server_error_reopened', null, $error);

        return response()->json(['data' => ['resolved' => $resolved]]);
    }
}
