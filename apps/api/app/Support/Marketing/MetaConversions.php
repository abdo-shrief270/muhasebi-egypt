<?php

declare(strict_types=1);

namespace App\Support\Marketing;

use Illuminate\Http\Request;

/**
 * Meta's Conversions API: the same events the pixel sends from the browser (CompleteRegistration,
 * Contact), sent by the server too — so they count when the browser blocks the pixel, and for shops
 * the team opens by hand (no browser at all). Sent with the same event id as the browser's copy, so
 * Meta counts it once. The person is matched by their phone, hashed (SHA-256) before it leaves; never
 * the number itself. Nothing is sent unless `services.meta` has a pixel id and an access token.
 */
final class MetaConversions
{
    public function configured(): bool
    {
        return (string) config('services.meta.pixel_id') !== '' && (string) config('services.meta.capi_token') !== '';
    }

    /**
     * @param  string  $actionSource  website | chat | system_generated … (Meta's list)
     * @param  array<string, mixed>  $signals  from {@see self::visitor()} (website events only)
     * @param  array<string, mixed>  $custom  custom_data
     */
    public function send(string $event, string $eventId, ?string $phone, ?string $externalId, string $actionSource, array $signals = [], array $custom = []): void
    {
        if (! $this->configured()) {
            return;
        }
        $user = array_filter([
            'ph' => $phone !== null && ($digits = preg_replace('/\D/', '', $phone)) !== '' ? [self::hash($digits)] : null,
            'external_id' => $externalId !== null ? [self::hash($externalId)] : null,
            'country' => [self::hash('eg')],
            'client_ip_address' => $signals['ip'] ?? null,
            'client_user_agent' => $signals['ua'] ?? null,
            'fbp' => $signals['fbp'] ?? null,
            'fbc' => $signals['fbc'] ?? null,
        ]);
        $payload = array_filter([
            'event_name' => $event,
            'event_time' => now()->getTimestamp(),
            'event_id' => $eventId,
            'action_source' => $actionSource,
            'event_source_url' => $signals['url'] ?? null,
            'user_data' => $user,
            'custom_data' => $custom ?: null,
        ]);
        SendMetaEvent::dispatch($payload)->afterCommit();
    }

    /**
     * What the browser tells about the visit: IP, user agent, Meta's _fbp / _fbc cookies (or the
     * fbclid of the ad click) and the page it happened on.
     *
     * @return array{ip: string|null, ua: string|null, fbp: string|null, fbc: string|null, url: string|null}
     */
    public static function visitor(Request $request, ?string $url = null): array
    {
        $fbc = self::cookie($request, '_fbc');
        $clickId = $request->input('fbclid');
        if ($fbc === null && is_string($clickId) && preg_match('/^[\w-]{10,500}$/', $clickId)) {
            $fbc = 'fb.1.'.now()->getTimestampMs().'.'.$clickId;
        }
        $page = $url ?? $request->headers->get('referer');

        return [
            'ip' => $request->ip(),
            'ua' => $request->userAgent() !== null ? mb_substr($request->userAgent(), 0, 500) : null,
            'fbp' => self::cookie($request, '_fbp'),
            'fbc' => $fbc,
            'url' => is_string($page) && preg_match('#^https?://#', $page) ? mb_substr(strtok($page, '?') ?: $page, 0, 500) : null,
        ];
    }

    private static function cookie(Request $request, string $name): ?string
    {
        // The pixel's own cookies, raw (never encrypted by the app).
        $value = $request->cookies->get($name);

        return is_string($value) && preg_match('/^fb\.\d\.\d+\.[\w.-]{1,500}$/', $value) ? $value : null;
    }

    private static function hash(string $value): string
    {
        return hash('sha256', mb_strtolower(trim($value)));
    }
}
