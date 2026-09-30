<?php

declare(strict_types=1);

namespace App\Modules\Suppliers\Contracts;

/**
 * Suppliers as other modules see them (e.g. returns sent back to them): who they are, what was
 * bought from whom, and postings to their account. Postings run inside the caller's transaction.
 */
interface SupplierAccounts
{
    /**
     * @param  list<string>  $supplierIds
     * @return array<string, array{id: string, name: string, phone: string|null}> keyed by id; unknown ones left out
     */
    public function find(array $supplierIds): array;

    /**
     * Active suppliers, by name.
     *
     * @return list<array{id: string, name: string, phone: string|null}>
     */
    public function active(): array;

    /**
     * Who each purchase was from.
     *
     * @param  list<string>  $purchaseIds
     * @return array<string, array{supplier_id: string, supplier_name: string, reference: string}> keyed by purchase id
     */
    public function purchases(array $purchaseIds): array;

    /**
     * The latest purchase of a variant from each supplier, newest first: its possible sources.
     *
     * @return list<array{supplier_id: string, supplier_name: string, purchase_id: string, reference: string, date: string, unit_cost: int, lot_id: string|null}>
     */
    public function recentPurchasesOf(string $variantId, int $limit = 5): array;

    /** Goods sent back and accepted: $amount (piasters) comes off what the shop owes the supplier. */
    public function creditReturn(string $supplierId, int $amount, string $refType, string $refId, string $note): void;

    /**
     * Goods sent back and paid back in money ($method: cash | wallet | instapay | bank_transfer): the
     * return and the refund are both on the statement, so the balance doesn't move.
     */
    public function refundReturn(string $supplierId, int $amount, string $method, string $refType, string $refId, string $note): void;
}
