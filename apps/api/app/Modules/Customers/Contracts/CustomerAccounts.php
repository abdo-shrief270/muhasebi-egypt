<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

use App\Support\Exceptions\DomainRuleException;

/**
 * Customer accounts for the modules that sell on credit (آجل). Postings run inside the caller's
 * transaction and lock the customer row.
 */
interface CustomerAccounts
{
    public function find(string $customerId): ?CustomerSummary;

    /**
     * What the customer didn't pay now goes on their account. $overLimitApproved: the owner (or a
     * manager) OK'd going past the customer's credit limit for this sale.
     *
     * @throws DomainRuleException customer_not_found (404), customer_inactive, credit_limit_exceeded
     */
    public function chargeSale(string $customerId, int $amount, string $saleId, string $reference, string $branchId, bool $overLimitApproved = false): void;

    /**
     * A repair bill (or what's left of it) on the customer's account.
     *
     * @throws DomainRuleException customer_not_found (404), customer_inactive, credit_limit_exceeded
     */
    public function chargeRepair(string $customerId, int $amount, string $ticketId, string $reference, string $branchId): void;

    /**
     * The customer with this phone (E.164 or 01…), or a new one — for intake screens where
     * the customer is typed in rather than picked. $consent (the customer agreed to having their
     * data kept) is recorded on a new customer, or on one never asked before.
     */
    public function findOrCreate(string $name, string $phone, ?bool $consent = null): CustomerSummary;

    /** A return settled against the account instead of in cash. May take the balance below zero (store credit). */
    public function creditReturn(string $customerId, int $amount, string $returnId, string $reference, string $branchId): void;

    /**
     * The markup (فوايد) of an installment plan, added to what the customer owes. Agreed with the
     * customer at signing, so it isn't held to the credit limit.
     *
     * @throws DomainRuleException customer_not_found (404)
     */
    public function chargeInstallmentMarkup(string $customerId, int $amount, string $planId, string $reference, string $branchId): void;

    /**
     * Money collected from the customer against their account, into the collector's drawer (cash
     * needs an open shift) — the same as «تحصيل» on the customer page. $source tags the
     * CustomerPaid event so the module that collected doesn't count it twice. Returns the
     * customer transaction id.
     *
     * @param  string  $method  cash | card | wallet | instapay
     *
     * @throws DomainRuleException customer_not_found (404)
     */
    public function collect(string $customerId, int $amount, string $method, string $branchId, ?string $note = null, ?string $source = null): string;

    /** Marks the customer as seen (a cash sale with them selected), for sorting. */
    public function touch(string $customerId): void;
}
