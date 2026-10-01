<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware: `feature:catalog.excel_import`. Runs after the tenant is resolved. */
final class EnsureFeatureEnabled
{
    public function __construct(private readonly FeatureAccess $features) {}

    public function handle(Request $request, Closure $next, string $key): Response
    {
        $this->features->ensure($key);

        return $next($request);
    }
}
