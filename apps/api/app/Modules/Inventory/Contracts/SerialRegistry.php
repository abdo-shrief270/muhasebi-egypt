<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Contracts;

/**
 * IMEIs / serial numbers of the units of products that track them. Called next to StockLedger,
 * inside the caller's transaction, with the same reference. Serials are normalised (spaces and
 * dashes dropped, upper case) and unique per shop.
 */
interface SerialRegistry
{
    /**
     * New units come in (purchase, opening). A serial already in stock anywhere in the shop is refused;
     * one that left before (sold, returned) comes back.
     *
     * @param  list<string>  $serials
     */
    public function receive(string $branchId, string $variantId, array $serials, StockReference $reference): void;

    /**
     * Units leave (sale, return to the supplier). Each must be in stock in the branch as that variant,
     * or unknown (stock from before serials were recorded: registered as it leaves).
     *
     * @param  list<string>  $serials
     */
    public function issue(string $branchId, string $variantId, array $serials, StockReference $reference): void;

    /**
     * A customer brings units back: in stock again, or kept aside as damaged.
     *
     * @param  list<string>  $serials  each must have left as that variant
     */
    public function takeBack(string $branchId, string $variantId, array $serials, StockReference $reference, bool $restock): void;

    /**
     * Serials in stock in a branch (e.g. for the POS to recognise a scanned IMEI while offline).
     *
     * @param  list<string>  $variantIds
     * @return array<string, list<string>> variant id => its serials; variants without any are left out
     */
    public function inStock(string $branchId, array $variantIds): array;

    /**
     * Where a unit is now and its story (bought, sold, returned…), oldest first; null when the shop never
     * recorded it. status: in_stock | out | damaged.
     *
     * @return array{serial: string, status: string, variant_id: string, branch_id: string|null,
     *               events: list<array{type: string, type_label: string, ref_type: string|null, ref_id: string|null, note: string|null, user_name: string|null, created_at: string}>}|null
     */
    public function history(string $serial): ?array;

    /**
     * @param  list<string>  $serials
     * @return list<string> normalised, in the same order
     */
    public function normalize(array $serials): array;
}
