<?php

declare(strict_types=1);

namespace App\Support\Time;

use Illuminate\Support\Carbon;

/**
 * Calendar days as the shops live them (Cairo), for comparing with `date` columns: the app runs in
 * UTC, so between midnight and 2–3 am Cairo time Carbon::today() would still be yesterday.
 */
final class ShopDay
{
    public const TZ = 'Africa/Cairo';

    /** Today in Cairo, as a midnight Carbon comparable with `date` casts. */
    public static function today(): Carbon
    {
        return Carbon::parse(Carbon::now(self::TZ)->toDateString());
    }
}
