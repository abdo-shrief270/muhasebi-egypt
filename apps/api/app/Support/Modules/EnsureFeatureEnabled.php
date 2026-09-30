<?php

declare(strict_types=1);

namespace App\Support\Modules;

use App\Support\Exceptions\DomainRuleException;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Route middleware: `feature:catalog.excel_import`. Runs after the tenant is resolved. */
final class EnsureFeatureEnabled
{
    public function __construct(
        private readonly FeatureAccess $features,
        private readonly ModuleRegistry $registry,
    ) {}

    public function handle(Request $request, Closure $next, string $key): Response
    {
        if (! $this->features->enabled($key)) {
            $label = $this->registry->feature($key)?->label ?? $key;

            throw new DomainRuleException("«{$label}» مقفولة في المحل ده. صاحب المحل يقدر يفتحها من صفحة المميزات.", 'feature_disabled', 403, ['feature' => $key]);
        }

        return $next($request);
    }
}
