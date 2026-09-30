<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Contracts;

/**
 * What shops sent the platform team (feedback) and what broke in their browsers (client errors),
 * across every shop. For the platform admins only (their controllers log each action).
 */
interface FeedbackInbox
{
    public const TYPES = ['problem' => 'مشكلة', 'suggestion' => 'اقتراح', 'question' => 'سؤال'];

    public const STATUSES = ['new' => 'جديدة', 'seen' => 'اتشافت', 'done' => 'خلصت'];

    /**
     * Newest first.
     *
     * @param  array{status?: string|null, type?: string|null, tenant_ids?: list<string>|null}  $filters  tenant_ids null = every shop
     * @return array{items: list<array<string, mixed>>, current_page: int, last_page: int, total: int}
     */
    public function feedback(array $filters, int $page = 1, int $perPage = 30): array;

    /**
     * Changes the status; null when there is no such feedback.
     *
     * @return array<string, mixed>|null the feedback after the change
     */
    public function setStatus(string $id, string $status, ?string $by): ?array;

    /**
     * Where the screenshot is on the local (private) disk.
     *
     * @return array{tenant_id: string, path: string}|null
     */
    public function screenshot(string $id): ?array;

    /**
     * @return array{new_feedback: int, open_errors: int, errors_24h: int}
     */
    public function counts(): array;

    /**
     * Client errors grouped by fingerprint (same error in every shop), most recent first.
     *
     * @return list<array{fingerprint: string, kind: string, message: string, source: string|null, stack: string|null, page: string|null, app_version: string|null, user_agent: string|null, count: int, tenant_ids: list<string>, first_seen_at: string, last_seen_at: string, resolved: bool}>
     */
    public function errorGroups(bool $includeResolved = false, ?string $search = null, int $limit = 100): array;

    /**
     * Marks an error fixed (or reopens it) in every shop; returns how many shops' rows changed.
     */
    public function resolveErrors(string $fingerprint, bool $resolved): int;
}
