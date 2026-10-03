<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Support;

/** The store's address: store.muhasebi.com/{slug}. Lowercase Latin letters, digits and dashes. */
final class Slugs
{
    public const PATTERN = '/^[a-z0-9](?:[a-z0-9-]{1,38}[a-z0-9])$/';

    /** Words that are pages of the store site itself, or would confuse customers. */
    public const RESERVED = [
        'admin', 'api', 'app', 'assets', 'cart', 'checkout', 'help', 'login', 'muhasebi', 'new', 'order', 'orders',
        'search', 'shop', 'shops', 'sitemap', 'static', 'store', 'stores', 'support', 'www', '_nuxt', 'robots',
    ];

    public static function valid(string $slug): bool
    {
        return preg_match(self::PATTERN, $slug) === 1 && ! str_contains($slug, '--') && ! in_array($slug, self::RESERVED, true);
    }
}
