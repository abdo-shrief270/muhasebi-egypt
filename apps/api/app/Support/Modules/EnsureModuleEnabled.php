<?php

declare(strict_types=1);

namespace App\Support\Modules;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware: `module:repairs`. Runs after the tenant is resolved.
 */
final class EnsureModuleEnabled
{
    public function __construct(
        private readonly ModuleRegistry $registry,
        private readonly ModuleAccess $access,
    ) {}

    public function handle(Request $request, Closure $next, string $key): Response
    {
        $state = $this->access->state($key);

        if (! $state->isUsable()) {
            throw new ModuleNotEnabledException($this->registry->get($key), $state);
        }

        return $next($request);
    }
}
