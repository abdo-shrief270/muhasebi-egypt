<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

use Illuminate\Support\Facades\Log;
use Minishlink\WebPush\Subscription;
use Minishlink\WebPush\WebPush;

/** Web Push with the VAPID keys in config/services.php (webpush). */
final class WebPushSender implements PushSender
{
    public function configured(): bool
    {
        return (string) config('services.webpush.public_key') !== '' && (string) config('services.webpush.private_key') !== '';
    }

    public function send(array $subscriptions, array $payload): array
    {
        if (! $this->configured() || $subscriptions === []) {
            return [];
        }

        $push = new WebPush(['VAPID' => [
            'subject' => (string) config('services.webpush.subject'),
            'publicKey' => (string) config('services.webpush.public_key'),
            'privateKey' => (string) config('services.webpush.private_key'),
        ]], ['TTL' => 6 * 3600, 'urgency' => $payload['urgent'] ?? false ? 'high' : 'normal']);

        $body = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: '{}';
        foreach ($subscriptions as $s) {
            $push->queueNotification(Subscription::create([
                'endpoint' => $s['endpoint'],
                'keys' => ['p256dh' => $s['p256dh'], 'auth' => $s['auth']],
            ]), $body);
        }

        $gone = [];
        foreach ($push->flush() as $report) {
            if ($report->isSubscriptionExpired()) {
                $gone[] = $report->getEndpoint();
            } elseif (! $report->isSuccess()) {
                Log::warning('push failed', ['reason' => $report->getReason()]);
            }
        }

        return $gone;
    }
}
