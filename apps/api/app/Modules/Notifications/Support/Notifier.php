<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use App\Modules\Notifications\Contracts\Notifications;
use App\Modules\Notifications\Jobs\SendPush;
use App\Modules\Notifications\Models\Notification;

/**
 * Adds a notification to the shop's bell and pushes it to the devices of the users who may see
 * it (and haven't muted its category). Call in the shop's context (listeners run in it).
 */
final class Notifier implements Notifications
{
    public function notify(string $type, string $title, ?string $body, string $icon, ?string $to, ?string $permission, bool $urgent = false): void
    {
        $notification = Notification::create([
            'type' => $type,
            'title' => $title,
            'body' => $body,
            'icon' => $icon,
            'to' => $to,
            'permission' => $permission,
            'created_at' => now(),
        ]);

        SendPush::dispatch($notification->tenant_id, $notification->id, $urgent)->afterCommit();
    }
}
