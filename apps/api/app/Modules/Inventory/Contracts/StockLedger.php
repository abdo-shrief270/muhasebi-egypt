<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

/**
 * The only way stock changes. Call it inside the caller's transaction so the document
 * (sale, purchase…) and the stock succeed or fail together.
 */
interface StockLedger
{
    /**
     * Puts stock in as a new lot at $unitCost (piasters) and updates the weighted average cost.
     *
     * @return string the lot id
     */
    public function receive(string $branchId, string $variantId, int $qty, int $unitCost, StockReference $reference): string;

    /**
     * Takes stock out, oldest lots first. Going below zero is allowed (selling before the
     * purchase is recorded); the missing part is costed at the average cost.
     */
    public function issue(string $branchId, string $variantId, int $qty, StockReference $reference): StockIssue;

    public function quantity(string $branchId, string $variantId): int;

    /**
     * @param  list<string>  $variantIds
     * @return list<string> the ones that have ever moved (in $branchId, or in any branch when null)
     */
    public function variantsWithHistory(array $variantIds, ?string $branchId = null): array;
}
