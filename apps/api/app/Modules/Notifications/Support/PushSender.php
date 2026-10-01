<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Support;

/** Delivers one payload to browsers' push endpoints. */
interface PushSender
{
    public function configured(): bool;

    /**
     * @param  list<array{endpoint: string, p256dh: string, auth: string}>  $subscriptions
     * @param  array<string, mixed>  $payload
     * @return list<string> endpoints that are gone (unsubscribed / expired) and should be forgotten
     */
    public function send(array $subscriptions, array $payload): array;
}
