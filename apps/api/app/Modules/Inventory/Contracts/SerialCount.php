<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

use App\Support\Exceptions\DomainRuleException;

/** A line of a product that tracks serials needs one serial per unit; other lines carry none. */
final class SerialCount
{
    /**
     * @param  list<string>|null  $serials
     * @return list<string>|null what to store on the line
     */
    public static function check(string $name, bool $tracksSerial, int $qty, ?array $serials, ?string $variantId = null): ?array
    {
        if (! $tracksSerial) {
            return null;
        }
        $serials = array_values(array_filter($serials ?? [], fn (string $s) => trim($s) !== ''));
        if (count($serials) !== $qty) {
            throw new DomainRuleException(
                "«{$name}» محتاج IMEI / سيريال لكل قطعة ({$qty}).",
                'serials_required',
                context: ['variant_id' => $variantId, 'qty' => $qty, 'given' => count($serials)],
            );
        }

        return $serials;
    }
}
