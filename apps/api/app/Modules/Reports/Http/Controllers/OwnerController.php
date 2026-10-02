<?php

declare(strict_types=1);

namespace App\Modules\Reports\Http\Controllers;

use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Reports\Owner\OwnerFeed;
use App\Modules\Reports\Owner\OwnerToday;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;

/** The owner app's «النهارده» and «اللي بيحصل» (owner_app.alerts: owner and managers). */
final class OwnerController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly BranchDirectory $branches,
    ) {}

    public function today(Request $request, OwnerToday $today): JsonResponse
    {
        $branchIds = $this->branchIds($request);
        $withProfit = (bool) $request->user()->can('reports.profit');
        $key = 'owner-today:'.$this->tenant->idOrFail().':'.md5(implode(',', $branchIds)).':'.(int) $withProfit;

        // Many owners refreshing their phones shouldn't each rerun the queries: 30 seconds is "live" enough.
        $data = Cache::remember($key, 30, fn () => $today->for($this->tenant->idOrFail(), $branchIds, $withProfit));

        return response()->json(['data' => $data, 'meta' => ['branches' => $this->branches->accessibleBranches($request->user())]]);
    }

    public function feed(Request $request, OwnerFeed $feed): JsonResponse
    {
        $request->validate([
            'kinds' => ['nullable', 'array'],
            'kinds.*' => [Rule::in(OwnerFeed::KINDS)],
            'before' => ['nullable', 'date'],
        ]);
        $kinds = $request->input('kinds') ?: OwnerFeed::KINDS;
        $before = $request->filled('before') ? CarbonImmutable::parse((string) $request->input('before')) : null;

        return response()->json(['data' => $feed->for($this->tenant->idOrFail(), $this->branchIds($request), array_values($kinds), $before, 25)]);
    }

    /**
     * @return list<string>
     */
    private function branchIds(Request $request): array
    {
        $allowed = $this->branches->accessibleBranches($request->user());
        $branch = (string) $request->query('branch', 'all');
        if ($branch !== 'all' && $branch !== '' && ! isset($allowed[$branch])) {
            throw new DomainRuleException('مش مسموح لك تشوف الفرع ده.', 'branch_forbidden', 403);
        }

        return $branch === 'all' || $branch === '' ? array_keys($allowed) : [$branch];
    }
}
