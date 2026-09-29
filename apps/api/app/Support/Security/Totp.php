<?php

declare(strict_types=1);

namespace App\Support\Security;

use InvalidArgumentException;

/**
 * Time-based one-time passwords (RFC 6238, HMAC-SHA1, 6 digits, 30 s steps) — what Google
 * Authenticator, Microsoft Authenticator, Authy… generate. Secrets are Base32 (RFC 4648).
 */
final class Totp
{
    public const DIGITS = 6;

    public const PERIOD = 30;

    private const BASE32 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';

    /** A new random secret: 20 bytes (160 bits, as RFC 4226 recommends), Base32 encoded. */
    public static function generateSecret(int $bytes = 20): string
    {
        return self::base32Encode(random_bytes($bytes));
    }

    /** The otpauth:// URL authenticator apps read from the QR code. */
    public static function provisioningUri(string $secret, string $account, string $issuer): string
    {
        return sprintf(
            'otpauth://totp/%s:%s?%s',
            rawurlencode($issuer),
            rawurlencode($account),
            http_build_query(['secret' => $secret, 'issuer' => $issuer, 'algorithm' => 'SHA1', 'digits' => self::DIGITS, 'period' => self::PERIOD], '', '&', PHP_QUERY_RFC3986),
        );
    }

    public static function step(?int $timestamp = null): int
    {
        return intdiv($timestamp ?? time(), self::PERIOD);
    }

    /** The code for a time step (defaults to now). */
    public static function code(string $secret, ?int $step = null, int $digits = self::DIGITS): string
    {
        $hash = hash_hmac('sha1', pack('J', $step ?? self::step()), self::base32Decode($secret), true);
        $offset = ord($hash[19]) & 0x0F;
        $binary = ((ord($hash[$offset]) & 0x7F) << 24)
            | (ord($hash[$offset + 1]) << 16)
            | (ord($hash[$offset + 2]) << 8)
            | ord($hash[$offset + 3]);

        return str_pad((string) ($binary % (10 ** $digits)), $digits, '0', STR_PAD_LEFT);
    }

    /**
     * Checks a code against the current step ± $window (clock drift). Returns the matching step,
     * or null. Steps at or before $afterStep are refused, so a code can't be used twice.
     */
    public static function verify(string $secret, string $code, ?int $timestamp = null, int $window = 1, ?int $afterStep = null): ?int
    {
        $code = preg_replace('/\s+/', '', $code) ?? '';
        if (preg_match('/^\d{'.self::DIGITS.'}$/', $code) !== 1) {
            return null;
        }

        $now = self::step($timestamp);
        for ($step = $now - $window; $step <= $now + $window; $step++) {
            if (($afterStep === null || $step > $afterStep) && hash_equals(self::code($secret, $step), $code)) {
                return $step;
            }
        }

        return null;
    }

    public static function base32Encode(string $bytes): string
    {
        if ($bytes === '') {
            return '';
        }

        $bits = '';
        foreach (str_split($bytes) as $char) {
            $bits .= str_pad(decbin(ord($char)), 8, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 5) as $chunk) {
            $out .= self::BASE32[(int) bindec(str_pad($chunk, 5, '0'))];
        }

        return $out;
    }

    public static function base32Decode(string $secret): string
    {
        $secret = strtoupper(preg_replace('/[\s=-]+/', '', $secret) ?? '');

        $bits = '';
        foreach (str_split($secret) as $char) {
            $value = strpos(self::BASE32, $char);
            if ($value === false) {
                throw new InvalidArgumentException('Invalid Base32 secret.');
            }
            $bits .= str_pad(decbin($value), 5, '0', STR_PAD_LEFT);
        }

        $out = '';
        foreach (str_split($bits, 8) as $byte) {
            if (strlen($byte) === 8) {
                $out .= chr((int) bindec($byte));
            }
        }

        return $out;
    }
}
