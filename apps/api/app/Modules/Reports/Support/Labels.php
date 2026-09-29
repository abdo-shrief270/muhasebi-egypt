<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

/** Arabic names for stored codes that belong to other modules' own enums. */
final class Labels
{
    private const METHODS = [
        'cash' => 'كاش',
        'card' => 'فيزا',
        'wallet' => 'محفظة',
        'instapay' => 'InstaPay',
        'credit' => 'آجل',
    ];

    public static function paymentMethod(string $method): string
    {
        return self::METHODS[$method] ?? $method;
    }

    /** Money over money as a percentage with one decimal; null when there's nothing to divide by. */
    public static function margin(int $profit, int $revenue): ?float
    {
        return $revenue !== 0 ? round($profit / $revenue * 100, 1) : null;
    }
}
