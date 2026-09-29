<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * The admin API exists only on the admin domain and, when configured, only for the admin's IPs.
 * Anyone else gets a plain 404, as if it weren't there.
 */
final class AdminGate
{
    public function handle(Request $request, Closure $next): Response
    {
        $domain = config('billing.admin.domain');
        if (filled($domain) && strcasecmp($request->getHost(), (string) $domain) !== 0) {
            abort(404);
        }

        $allowed = (array) config('billing.admin.allowed_ips');
        if ($allowed !== [] && ! IpUtils::checkIp((string) $request->ip(), $allowed)) {
            abort(404);
        }

        $response = $next($request);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');
        $response->headers->set('Cache-Control', 'no-store');

        return $response;
    }
}
