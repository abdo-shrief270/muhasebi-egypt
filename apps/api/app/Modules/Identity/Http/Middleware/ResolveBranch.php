<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Middleware;

use App\Modules\Identity\BranchAccess;
use App\Modules\Identity\Models\User;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentBranch;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route middleware `branch`: picks the branch from X-Branch-Id (or the user's default)
 * and makes sure the user may work there. Runs after `tenant`.
 */
final class ResolveBranch
{
    public function __construct(
        private readonly BranchAccess $access,
        private readonly CurrentBranch $branch,
    ) {}

    public function handle(Request $request, Closure $next): Response
    {
        /** @var User $user */
        $user = $request->user();

        $branchId = $this->access->resolve($user, $request->header('X-Branch-Id'))
            ?? throw new DomainRuleException('مفيش فرع متاح لحسابك. كلّم صاحب المحل.', 'branch_forbidden', 403);

        $this->branch->set($branchId);

        return $next($request);
    }
}
