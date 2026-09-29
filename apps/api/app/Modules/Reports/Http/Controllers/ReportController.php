<?php

declare(strict_types=1);

namespace App\Modules\Reports\Http\Controllers;

use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\Reports\Http\Requests\RunReportRequest;
use App\Modules\Reports\Support\Report;
use App\Modules\Reports\Support\ReportQuery;
use App\Modules\Reports\Support\ReportRegistry;
use App\Modules\Reports\Support\XlsxReport;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ReportController
{
    public function __construct(
        private readonly ReportRegistry $reports,
        private readonly BranchDirectory $branches,
        private readonly CurrentTenant $tenant,
    ) {}

    /** The reports this user may open, and the branches they may pick from. */
    public function index(Request $request): JsonResponse
    {
        $user = $request->user() ?? abort(401);

        return response()->json(['data' => [
            'reports' => array_map(fn (Report $r): array => [
                'key' => $r->key(),
                'title' => $r->title(),
                'description' => $r->description(),
                'group' => $r->group(),
                'uses_dates' => $r->usesDates(),
                'uses_branches' => $r->usesBranches(),
                'options' => $r->options(),
            ], $this->reports->visibleTo($user)),
            'branches' => array_map(fn (string $id, string $name) => ['id' => $id, 'name' => $name], array_keys($b = $this->branches->accessibleBranches($user)), $b),
        ]]);
    }

    public function show(RunReportRequest $request, string $report): JsonResponse
    {
        [$definition, $query] = $this->prepare($request, $report);

        return response()->json(['data' => [
            'key' => $definition->key(),
            'title' => $definition->title(),
            'from' => $definition->usesDates() ? $query->from->toDateString() : null,
            'to' => $definition->usesDates() ? $query->to->toDateString() : null,
            'branches' => $definition->usesBranches() ? array_values($query->branches) : null,
            ...$definition->run($query)->toArray(),
        ]]);
    }

    public function export(RunReportRequest $request, string $report, XlsxReport $xlsx, ShopDirectory $shops): BinaryFileResponse
    {
        [$definition, $query] = $this->prepare($request, $report);
        $path = tempnam(sys_get_temp_dir(), 'report').'.xlsx';
        $xlsx->write($path, $definition, $query, $definition->run($query), $shops->find($query->tenantId)->name ?? '');

        $name = $definition->key().($definition->usesDates() ? "-{$query->from->toDateString()}_{$query->to->toDateString()}" : '-'.now('Africa/Cairo')->toDateString()).'.xlsx';

        return response()->download($path, $name)->deleteFileAfterSend();
    }

    /**
     * @return array{0: Report, 1: ReportQuery}
     */
    private function prepare(RunReportRequest $request, string $key): array
    {
        $user = $request->user() ?? abort(401);
        $definition = $this->reports->find($key) ?? throw new DomainRuleException('التقرير ده مش موجود.', 'report_not_found', 404);
        abort_unless($this->reports->canSee($user, $definition), 403);

        $today = CarbonImmutable::now(ReportQuery::TZ)->startOfDay();
        $from = $request->filled('from') ? CarbonImmutable::parse((string) $request->validated('from'), ReportQuery::TZ) : $today->startOfMonth();
        $to = $request->filled('to') ? CarbonImmutable::parse((string) $request->validated('to'), ReportQuery::TZ) : $today;
        if ($from->diffInDays($to) > 366) {
            throw new DomainRuleException('أقصى فترة للتقرير سنة.', 'period_too_long');
        }

        $allowed = $this->branches->accessibleBranches($user);
        $branch = (string) ($request->validated('branch') ?? 'all');
        if ($branch !== 'all' && $branch !== '' && ! isset($allowed[$branch])) {
            throw new DomainRuleException('مش مسموح لك تشوف الفرع ده.', 'branch_forbidden', 403);
        }
        $selected = $branch === 'all' || $branch === '' ? $allowed : [$branch => $allowed[$branch]];

        // Choices outside a report's own options fall back to its default.
        $options = [];
        foreach ($definition->options() as $option) {
            $value = $request->validated('options')[$option['key']] ?? null;
            if (in_array($value, array_column($option['choices'], 'value'), true)) {
                $options[$option['key']] = $value;
            }
        }

        return [$definition, new ReportQuery(
            tenantId: $this->tenant->idOrFail(),
            from: $from,
            to: $to,
            branches: $selected,
            withProfit: $user->can('reports.profit'),
            withCost: $user->can('products.view_cost'),
            options: $options,
        )];
    }
}
