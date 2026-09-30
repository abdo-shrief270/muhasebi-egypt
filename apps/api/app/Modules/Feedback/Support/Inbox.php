<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Support;

use App\Modules\Feedback\Contracts\FeedbackInbox;
use App\Modules\Feedback\Models\ClientError;
use App\Modules\Feedback\Models\Feedback;
use Illuminate\Support\Carbon;

/** Feedback and client errors across every shop, for the platform admins. */
final class Inbox implements FeedbackInbox
{
    public function feedback(array $filters, int $page = 1, int $perPage = 30): array
    {
        $tenantIds = $filters['tenant_ids'] ?? null;
        $paginator = Feedback::withoutTenancy()
            ->when(filled($filters['status'] ?? null), fn ($q) => $q->where('status', $filters['status']))
            ->when(filled($filters['type'] ?? null), fn ($q) => $q->where('type', $filters['type']))
            ->when($tenantIds !== null, fn ($q) => $q->whereIn('tenant_id', $tenantIds))
            ->orderByDesc('created_at')->orderByDesc('id')
            ->paginate($perPage, page: max(1, $page));

        return [
            'items' => $paginator->getCollection()->map(fn (Feedback $f): array => $this->present($f))->values()->all(),
            'current_page' => $paginator->currentPage(),
            'last_page' => $paginator->lastPage(),
            'total' => $paginator->total(),
        ];
    }

    public function setStatus(string $id, string $status, ?string $by): ?array
    {
        $feedback = Feedback::withoutTenancy()->find($id);
        if ($feedback === null) {
            return null;
        }
        if ($feedback->status !== $status) {
            $feedback->update(['status' => $status, 'status_changed_at' => now(), 'status_changed_by' => $by]);
        }

        return $this->present($feedback);
    }

    public function screenshot(string $id): ?array
    {
        $feedback = Feedback::withoutTenancy()->find($id);

        return $feedback?->screenshot_path !== null ? ['tenant_id' => $feedback->tenant_id, 'path' => $feedback->screenshot_path] : null;
    }

    public function counts(): array
    {
        return [
            'new_feedback' => Feedback::withoutTenancy()->where('status', 'new')->count(),
            'open_errors' => ClientError::withoutTenancy()->whereNull('resolved_at')->distinct()->count('fingerprint'),
            'errors_24h' => ClientError::withoutTenancy()->where('last_seen_at', '>=', now()->subDay())->distinct()->count('fingerprint'),
        ];
    }

    public function errorGroups(bool $includeResolved = false, ?string $search = null, int $limit = 100): array
    {
        $groups = ClientError::withoutTenancy()
            ->when(! $includeResolved, fn ($q) => $q->whereNull('resolved_at'))
            ->when(filled($search), fn ($q) => $q->where('message', 'ilike', '%'.addcslashes((string) $search, '%_\\').'%'))
            ->selectRaw('fingerprint, sum(count) as total, array_to_json(array_agg(distinct tenant_id)) as tenants,
                min(first_seen_at) as first_seen, max(last_seen_at) as last_seen, bool_and(resolved_at is not null) as resolved')
            ->groupBy('fingerprint')
            ->orderByDesc('last_seen')
            ->limit($limit)
            ->get();

        // The latest occurrence of each group, for its message, stack and browser.
        $latest = ClientError::withoutTenancy()->whereIn('fingerprint', $groups->pluck('fingerprint'))
            ->orderByDesc('last_seen_at')->get()->unique('fingerprint')->keyBy('fingerprint');

        return $groups->map(function ($g) use ($latest): array {
            /** @var ClientError $row */
            $row = $latest[$g->fingerprint];

            return [
                'fingerprint' => $g->fingerprint,
                'kind' => $row->kind,
                'message' => $row->message,
                'source' => $row->source,
                'stack' => $row->stack,
                'page' => $row->page,
                'app_version' => $row->app_version,
                'user_agent' => $row->user_agent,
                'count' => (int) $g->total,
                'tenant_ids' => array_values((array) json_decode((string) $g->tenants, true)),
                'first_seen_at' => Carbon::parse($g->first_seen)->toIso8601String(),
                'last_seen_at' => Carbon::parse($g->last_seen)->toIso8601String(),
                'resolved' => (bool) $g->resolved,
            ];
        })->values()->all();
    }

    public function resolveErrors(string $fingerprint, bool $resolved): int
    {
        return ClientError::withoutTenancy()->where('fingerprint', $fingerprint)->update(['resolved_at' => $resolved ? now() : null]);
    }

    /** @return array<string, mixed> */
    private function present(Feedback $f): array
    {
        return [
            'id' => $f->id,
            'tenant_id' => $f->tenant_id,
            'user_name' => $f->user_name,
            'type' => $f->type,
            'type_label' => self::TYPES[$f->type] ?? $f->type,
            'message' => $f->message,
            'page' => $f->page,
            'app_version' => $f->app_version,
            'user_agent' => $f->user_agent,
            'screen' => $f->screen,
            'has_screenshot' => $f->screenshot_path !== null,
            'status' => $f->status,
            'status_label' => self::STATUSES[$f->status] ?? $f->status,
            'status_changed_at' => $f->status_changed_at?->toIso8601String(),
            'status_changed_by' => $f->status_changed_by,
            'created_at' => $f->created_at->toIso8601String(),
        ];
    }
}
