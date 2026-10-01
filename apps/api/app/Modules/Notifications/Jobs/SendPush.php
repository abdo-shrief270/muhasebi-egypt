<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Jobs;

use App\Modules\Notifications\Models\Notification;
use App\Modules\Notifications\Models\NotificationPreference;
use App\Modules\Notifications\Models\PushSubscription;
use App\Modules\Notifications\Support\Categories;
use App\Modules\Notifications\Support\PushSender;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Auth\Access\Authorizable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Carbon;

/**
 * Pushes one notification to every device of the shop's users who may see it: they have its
 * permission, haven't muted its category, and aren't in their quiet hours (urgent ones ignore those).
 */
final class SendPush implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public function __construct(
        public readonly string $tenantId,
        public readonly string $notificationId,
        public readonly bool $urgent = false,
    ) {}

    public function handle(PushSender $sender, CurrentTenant $tenant): void
    {
        if (! $sender->configured()) {
            return;
        }

        $tenant->runAs($this->tenantId, function () use ($sender): void {
            $notification = Notification::query()->find($this->notificationId);
            if ($notification === null) {
                return;
            }
            $category = Categories::of($notification->type);
            $now = Carbon::now('Africa/Cairo')->format('H:i');
            $users = auth()->createUserProvider('users');

            $subscriptions = PushSubscription::query()->get()->groupBy('user_id');
            $preferences = NotificationPreference::query()->whereIn('user_id', $subscriptions->keys())->get()->keyBy('user_id');
            $targets = [];
            foreach ($subscriptions as $userId => $devices) {
                $user = $users->retrieveById($userId);
                if (! $user instanceof Authorizable || ! $user->getAttribute('is_active')) {
                    continue;
                }
                if ($notification->permission !== null && ! $user->can($notification->permission)) {
                    continue;
                }
                $pref = $preferences->get($userId);
                if ($pref !== null && $category !== null && in_array($category, $pref->muted, true)) {
                    continue;
                }
                if ($pref !== null && ! $this->urgent && $pref->isQuietAt($now)) {
                    continue;
                }
                foreach ($devices as $device) {
                    $targets[] = ['endpoint' => $device->endpoint, 'p256dh' => $device->p256dh, 'auth' => $device->auth];
                }
            }
            if ($targets === []) {
                return;
            }

            $gone = $sender->send($targets, [
                'title' => $notification->title,
                'body' => $notification->body,
                'url' => $notification->to ?? '/',
                'tag' => $notification->id,
                'urgent' => $this->urgent,
            ]);
            PushSubscription::query()->whereIn('endpoint', array_column($targets, 'endpoint'))->update(['last_sent_at' => now()]);
            if ($gone !== []) {
                PushSubscription::query()->whereIn('endpoint', $gone)->delete();
            }
        });
    }
}
