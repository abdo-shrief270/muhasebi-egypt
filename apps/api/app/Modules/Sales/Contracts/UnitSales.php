<?php

declare(strict_types=1);

namespace App\Modules\Sales\Contracts;

/**
 * The last sale of single units (a used phone is a variant of its own): which invoice and for how
 * much, for modules that follow what happened to one unit.
 */
interface UnitSales
{
    /**
     * The latest invoice line of each variant that is still sold (not fully returned).
     *
     * @param  list<string>  $variantIds
     * @return array<string, UnitSale> variant id => its sale; unsold ones are left out
     */
    public function lastSales(array $variantIds): array;
}
