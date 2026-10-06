<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

/**
 * The store's name in its address: {slug}.muhasebi.com (or store.muhasebi.com/{slug}). Lowercase
 * Latin letters, digits and dashes — also a valid DNS label.
 */
final class Slugs
{
    public const PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/';

    /** Words that are pages of the store site itself, or would confuse customers. */
    public const RESERVED = [
        // Pages of the store site.
        'assets', 'cart', 'checkout', 'login', 'new', 'order', 'orders', 'robots', 'search', 'sitemap', 'static', '_nuxt',
        // Subdomains of the platform ({slug}.muhasebi.com must never shadow them).
        'account', 'admin', 'api', 'app', 'beta', 'billing', 'blog', 'cdn', 'dashboard', 'dev', 'docs', 'ftp', 'help',
        'm', 'mail', 'muhasebi', 'my', 'ns1', 'ns2', 'owner', 'pay', 'shop', 'shops', 'smtp', 'staging', 'status',
        'store', 'stores', 'souq', 'souk', 'market', 'support', 'test', 'www',
    ];

    public static function valid(string $slug): bool
    {
        return preg_match(self::PATTERN, $slug) === 1 && ! str_contains($slug, '--') && ! in_array($slug, self::RESERVED, true);
    }

    /** The store's public address. */
    public static function url(string $slug): string
    {
        $url = (string) config('services.store.url');

        return str_contains($url, '{slug}') ? str_replace('{slug}', $slug, $url) : rtrim($url, '/').'/'.$slug;
    }

    /** The slug a subdomain of the stores' host stands for ("elnour.muhasebi.com" → "elnour"), or null. */
    public static function fromHost(string $host): ?string
    {
        $base = strtolower((string) config('services.store.host'));
        $host = strtolower(rtrim($host, '.'));
        if ($base === '' || ! str_ends_with($host, '.'.$base)) {
            return null;
        }
        $label = substr($host, 0, -strlen('.'.$base));

        return self::valid($label) ? $label : null;
    }
}
