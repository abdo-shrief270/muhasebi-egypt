<?php

declare(strict_types=1);

namespace App\Modules\Feedback\Support;

/**
 * Keeps personal data out of what the browser reports (error messages, stacks, paths): e-mails,
 * long digit runs (phones, IMEIs, barcodes) and ids go; query strings and fragments go. Also makes
 * the same error from different records group together.
 */
final class Scrub
{
    public static function text(?string $value, int $max): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim($value);
        $value = (string) preg_replace('/[\w.+-]+@[\w-]+(\.[\w-]+)+/u', '[email]', $value);
        $value = (string) preg_replace('/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i', ':id', $value);
        $value = (string) preg_replace('/\+?\d[\d\s-]{5,}\d/', '#', $value);

        return $value === '' ? null : mb_substr($value, 0, $max);
    }

    /** A path inside the app: no host, query string or fragment; ids folded. */
    public static function path(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }
        $path = (string) (parse_url(trim($value), PHP_URL_PATH) ?? '');
        $path = (string) preg_replace('/[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}/i', ':id', $path);
        $path = (string) preg_replace('#/\d{4,}#', '/:n', $path);

        return $path === '' ? null : mb_substr($path, 0, 255);
    }

    /** A source file:line:column, without the query string (cache busters). */
    public static function source(?string $value): ?string
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return mb_substr((string) preg_replace('/\?[^:\s]*/', '', trim($value)), 0, 255);
    }
}
