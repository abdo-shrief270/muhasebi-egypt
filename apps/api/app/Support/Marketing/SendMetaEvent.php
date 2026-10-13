<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/** One event to Meta's Conversions API, off the request; a failure is logged, never thrown at a user. */
final class SendMetaEvent implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 60;

    /** @param array<string, mixed> $event */
    public function __construct(public readonly array $event) {}

    public function handle(): void
    {
        $pixel = (string) config('services.meta.pixel_id');
        $token = (string) config('services.meta.capi_token');
        if ($pixel === '' || $token === '') {
            return;
        }
        $body = array_filter([
            'data' => [$this->event],
            'test_event_code' => config('services.meta.test_event_code') ?: null,
        ]);
        $response = Http::timeout(10)
            ->post(sprintf('https://graph.facebook.com/%s/%s/events?access_token=%s', config('services.meta.graph_version'), rawurlencode($pixel), rawurlencode($token)), $body);
        if ($response->serverError()) {
            $response->throw();   // retried
        }
        if ($response->failed()) {
            Log::warning('Meta Conversions API refused an event', ['event' => $this->event['event_name'] ?? null, 'status' => $response->status(), 'error' => $response->json('error.message')]);
        }
    }
}
