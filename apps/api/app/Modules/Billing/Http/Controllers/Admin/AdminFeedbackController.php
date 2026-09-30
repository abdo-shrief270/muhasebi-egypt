<?php

declare(strict_types=1);

namespace App\Modules\Billing\Http\Controllers\Admin;

use App\Modules\Billing\Support\AdminLog;
use App\Modules\Feedback\Contracts\FeedbackInbox;
use App\Modules\Identity\Contracts\PlatformShops;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Platform admins: what shops sent from «ابعت ملاحظة» and the errors their browsers hit
 * («أخطاء الواجهة»). Every action is logged (AdminLog).
 */
final class AdminFeedbackController
{
    public function __construct(
        private readonly FeedbackInbox $inbox,
        private readonly PlatformShops $shops,
        private readonly AdminLog $log,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(array_keys(FeedbackInbox::STATUSES))],
            'type' => ['nullable', Rule::in(array_keys(FeedbackInbox::TYPES))],
            'q' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);
        $result = $this->inbox->feedback([
            'status' => $data['status'] ?? null,
            'type' => $data['type'] ?? null,
            'tenant_ids' => filled($data['q'] ?? null) ? $this->shops->searchIds((string) $data['q']) : null,
        ], (int) ($data['page'] ?? 1));
        $shops = $this->shops->details(array_values(array_unique(array_column($result['items'], 'tenant_id'))));

        return response()->json([
            'data' => array_map(fn (array $f): array => [...$f, 'shop' => $this->shopLine($shops[$f['tenant_id']] ?? null)], $result['items']),
            'meta' => ['current_page' => $result['current_page'], 'last_page' => $result['last_page'], 'total' => $result['total'], ...$this->inbox->counts()],
        ]);
    }

    public function update(Request $request, string $feedback): JsonResponse
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(FeedbackInbox::STATUSES))]]);
        $row = $this->inbox->setStatus($feedback, $data['status'], $request->user()?->getAttribute('name')) ?? abort(404);
        $this->log->record('feedback_status', $row['tenant_id'], $row['id'], ['status' => $data['status']]);

        return response()->json(['data' => [...$row, 'shop' => $this->shopLine($this->shops->details([$row['tenant_id']])[$row['tenant_id']] ?? null)]]);
    }

    /** The screenshot, only through here (the admin's token), never a public URL. */
    public function screenshot(string $feedback): StreamedResponse
    {
        $shot = $this->inbox->screenshot($feedback);
        abort_if($shot === null || ! Storage::disk('local')->exists($shot['path']), 404);
        $this->log->record('feedback_screenshot_viewed', $shot['tenant_id'], $feedback);

        return Storage::disk('local')->response($shot['path'], headers: ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }

    public function errors(Request $request): JsonResponse
    {
        $data = $request->validate([
            'resolved' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);
        $groups = $this->inbox->errorGroups((bool) ($data['resolved'] ?? false), $data['q'] ?? null);
        $shops = $this->shops->details(array_values(array_unique(array_merge(...array_column($groups, 'tenant_ids')))));

        return response()->json([
            'data' => array_map(fn (array $g): array => [
                ...$g,
                'shops' => array_values(array_filter(array_map(fn (string $id) => $this->shopLine($shops[$id] ?? null), $g['tenant_ids']))),
            ], $groups),
            'meta' => $this->inbox->counts(),
        ]);
    }

    public function resolve(Request $request, string $fingerprint): JsonResponse
    {
        $data = $request->validate(['resolved' => ['required', 'boolean']]);
        $changed = $this->inbox->resolveErrors($fingerprint, (bool) $data['resolved']);
        abort_if($changed === 0, 404);
        $this->log->record($data['resolved'] ? 'client_error_resolved' : 'client_error_reopened', details: ['fingerprint' => $fingerprint, 'shops' => $changed]);

        return response()->json(['data' => ['fingerprint' => $fingerprint, 'resolved' => (bool) $data['resolved']]]);
    }

    /**
     * @param  array<string, mixed>|null  $shop
     * @return array{id: string, name: string, code: string}|null
     */
    private function shopLine(?array $shop): ?array
    {
        return $shop === null ? null : ['id' => $shop['id'], 'name' => $shop['name'], 'code' => $shop['code']];
    }
}
