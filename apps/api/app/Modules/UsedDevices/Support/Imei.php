<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Support;

/** A phone's IMEI: 15 digits whose last one is a Luhn check digit (*#06# shows it). */
final class Imei
{
    public static function normalize(string $raw): string
    {
        return (string) preg_replace('/\D+/', '', NationalId::normalize($raw));
    }

    public static function isValid(string $raw): bool
    {
        $imei = self::normalize($raw);
        if (! preg_match('/^\d{15}$/', $imei)) {
            return false;
        }
        $sum = 0;
        for ($i = 0; $i < 15; $i++) {
            $d = (int) $imei[$i];
            if ($i % 2 === 1) {
                $d *= 2;
                $d = $d > 9 ? $d - 9 : $d;
            }
            $sum += $d;
        }

        return $sum % 10 === 0;
    }
}
