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
        // enabled(), not the raw state: a module still «قريباً» stays closed whatever the shop's row says.
        if (! $this->access->enabled($key)) {
            throw new ModuleNotEnabledException($this->registry->get($key), $this->access->state($key));
        }

        return $next($request);
    }
}
