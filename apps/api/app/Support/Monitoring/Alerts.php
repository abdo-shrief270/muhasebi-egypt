<?php

declare(strict_types=1);

namespace App\Support\Monitoring;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Messages to the platform team: a Telegram chat (services.telegram.bot_token / chat_id), else
 * only the log. Never throws — an alert must not break what it reports on.
 */
final class Alerts
{
    public function configured(): bool
    {
        return (string) config('services.telegram.bot_token') !== '' && (string) config('services.telegram.chat_id') !== '';
    }

    public function send(string $text): bool
    {
        return $this->deliver($text) === null;
    }

    /** Sends the message; null when Telegram took it, else why not (Telegram's own description). */
    public function deliver(string $text): ?string
    {
        $text = '['.config('app.name').'] '.mb_substr($text, 0, 3500);
        if (! $this->configured()) {
            Log::warning('alert: '.$text);

            return 'TELEGRAM_BOT_TOKEN / TELEGRAM_CHAT_ID are not set';
        }
        try {
            $response = Http::timeout(4)->asForm()->post('https://api.telegram.org/bot'.config('services.telegram.bot_token').'/sendMessage', [
                'chat_id' => config('services.telegram.chat_id'),
                'text' => $text,
                'disable_web_page_preview' => 'true',
            ]);

            return $response->successful() ? null : (string) ($response->json('description') ?? 'HTTP '.$response->status());
        } catch (\Throwable $e) {
            Log::warning('alert not sent: '.$e->getMessage());

            return $e->getMessage();
        }
    }
}
