<?php

declare(strict_types=1);

namespace App\Support\Privacy;

use Illuminate\Contracts\Database\Query\Builder;

/**
 * A keyed hash of a phone number, so an erasure event can say "this phone" to other modules
 * without writing the number itself into the outbox. Matching runs in SQL (PostgreSQL sha256()).
 */
final class PhoneFingerprint
{
    public static function of(?string $phone): ?string
    {
        return $phone === null || $phone === '' ? null : hash('sha256', self::key().$phone);
    }

    /**
     * Adds "the phone in $column has this fingerprint" to the query.
     *
     * @template T of Builder
     *
     * @param  T  $query
     * @return T
     */
    public static function where(Builder $query, string $column, string $fingerprint): Builder
    {
        return $query->whereRaw("encode(sha256(convert_to(? || {$column}, 'UTF8')), 'hex') = ?", [self::key(), $fingerprint]);
    }

    private static function key(): string
    {
        return hash_hmac('sha256', 'phone-fingerprint', (string) config('app.key'));
    }
}
