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
     * Takes stock out, oldest lots first ($fromLotId first when given, e.g. returning a purchase
     * to its supplier). Going below zero is allowed (selling before the purchase is recorded);
     * the missing part is costed at the average cost.
     */
    public function issue(string $branchId, string $variantId, int $qty, StockReference $reference, ?string $fromLotId = null): StockIssue;

    /**
     * Puts units that left stock back into the lot they came from (e.g. a unit the supplier refused
     * to take back), so that lot keeps telling where they were bought. Without a usable lot it is a
     * plain receive at $unitCost.
     *
     * @return string the lot id
     */
    public function restore(string $branchId, string $variantId, int $qty, int $unitCost, ?string $lotId, StockReference $reference): string;

    /**
     * What a document took out of stock for a variant (a sale, a repair ticket…): the lots and costs.
     *
     * @return list<StockPortion>
     */
    public function issuedFor(string $refType, string $refId, string $variantId): array;

    /**
     * Where lots came from: the movement type that received them (purchase, opening…) and its document.
     *
     * @param  list<string>  $lotIds
     * @return array<string, array{source_type: string, source_id: string|null, unit_cost: int, received_at: string}> keyed by lot id
     */
    public function lotOrigins(array $lotIds): array;

    /** The lot a document received for a variant (e.g. the lot of a purchase line), newest first. */
    public function lotOf(MovementType $sourceType, string $sourceId, string $variantId): ?string;

    /**
     * @param  list<string>  $variantIds
     * @return array<string, int> variant id => average cost (piasters) in the branch; unknown ones left out
     */
    public function averageCosts(string $branchId, array $variantIds): array;

    public function quantity(string $branchId, string $variantId): int;

    /**
     * @param  list<string>  $variantIds
     * @return array<string, int> variant id => quantity in the branch (0 when it never moved)
     */
    public function quantities(string $branchId, array $variantIds): array;

    /**
     * @param  list<string>  $variantIds
     * @return list<string> the ones that have ever moved (in $branchId, or in any branch when null)
     */
    public function variantsWithHistory(array $variantIds, ?string $branchId = null): array;
}
