<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Middleware;

use App\Modules\Repairs\Models\RepairTicket;
use App\Support\Tenancy\CurrentTenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public tracking link has no login: the shop is the one that owns the ticket behind the
 * token. Runs before `module:repairs`, so a shop that turned repairs off stops sharing too.
 */
final class ResolveTicketShop
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function handle(Request $request, Closure $next): Response
    {
        $tenantId = RepairTicket::withoutTenancy()->where('public_token', (string) $request->route('token'))->value('tenant_id');
        if ($tenantId === null) {
            return response()->json(['message' => 'التذكرة دي مش موجودة.', 'code' => 'ticket_not_found'], 404);
        }
        $this->tenant->set((string) $tenantId);

        return $next($request);
    }
}
