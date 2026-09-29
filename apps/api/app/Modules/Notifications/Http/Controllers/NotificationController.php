<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Http\Controllers;

use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\NotificationRead;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * The signed-in user's bell: the shop's notifications this user may see (each one's own
 * permission), unread first. Read state is per user.
 */
final class NotificationController
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function index(Request $request): JsonResponse
    {
        $userId = (string) $request->user()?->getAuthIdentifier();

        $page = $this->visible($request)
            ->select('notifications.*')
            ->selectRaw('exists (select 1 from notification_reads r where r.notification_id = notifications.id and r.user_id = ?) as is_read', [$userId])
            ->orderBy('is_read')
            ->orderByDesc('seq')
            ->paginate(min(50, max(5, $request->integer('per_page', 20))));

        return response()->json([
            'data' => $page->getCollection()->map(fn (Notification $n): array => [
                'id' => $n->id,
                'type' => $n->type,
                'title' => $n->title,
                'body' => $n->body,
                'icon' => $n->icon,
                'to' => $n->to,
                'read' => (bool) $n->getAttribute('is_read'),
                'created_at' => $n->created_at->toIso8601String(),
            ])->all(),
            'meta' => [
                'current_page' => $page->currentPage(),
                'last_page' => $page->lastPage(),
                'per_page' => $page->perPage(),
                'total' => $page->total(),
                'unread' => $this->unreadCount($request),
            ],
        ]);
    }

    public function unread(Request $request): JsonResponse
    {
        return response()->json(['data' => ['count' => $this->unreadCount($request)]]);
    }

    public function read(Request $request, string $notification): JsonResponse
    {
        $row = $this->visible($request)->whereKey($notification)->firstOrFail();
        $this->markRead($request, [$row->id]);

        return response()->json(['data' => ['count' => $this->unreadCount($request)]]);
    }

    public function readAll(Request $request): JsonResponse
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        $this->visible($request)->unreadBy($userId)->select('id')->chunkById(500, function ($rows) use ($request): void {
            $this->markRead($request, $rows->pluck('id')->all());
        });

        return response()->json(['data' => ['count' => 0]]);
    }

    /**
     * @return Builder<Notification>
     */
    private function visible(Request $request): Builder
    {
        $user = $request->user();
        abort_unless($user instanceof Authorizable, 401);

        return Notification::query()->visibleTo($user);
    }

    private function unreadCount(Request $request): int
    {
        return $this->visible($request)->unreadBy((string) $request->user()?->getAuthIdentifier())->count();
    }

    /**
     * @param  list<string>  $ids
     */
    private function markRead(Request $request, array $ids): void
    {
        $userId = (string) $request->user()?->getAuthIdentifier();
        $tenantId = $this->tenant->idOrFail();
        DB::table((new NotificationRead)->getTable())->insertOrIgnore(array_map(fn (string $id): array => [
            'notification_id' => $id,
            'user_id' => $userId,
            'tenant_id' => $tenantId,
            'read_at' => now(),
        ], $ids));
    }
}
