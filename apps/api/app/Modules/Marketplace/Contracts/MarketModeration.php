<?php

declare(strict_types=1);

namespace App\Modules\Marketplace\Contracts;

/** Visitors' reports on offers («في حاجة غلط في العرض ده») and what the platform admins do with them. */
interface MarketModeration
{
    public const REASONS = [
        'wrong_price' => 'السعر غلط',
        'not_available' => 'مش موجود في المحل',
        'fake' => 'شكله مضروب / مش أصلي',
        'offensive' => 'كلام أو صور مش لايقة',
        'other' => 'حاجة تانية',
    ];

    /**
     * @param  array<string, mixed>|null  $offer  the offer's document (tenant, variant, title), when the index answers
     * @return bool false when the same visitor already reported it today
     */
    public function report(string $offerId, ?array $offer, string $reason, ?string $note, ?string $contact, string $ip): bool;

    /** @return list<array<string, mixed>> newest first */
    public function reports(?string $status = 'new', int $limit = 200): array;

    public function openCount(): int;

    /**
     * Dismiss it, or take the offer out of the market (all its open reports close with it).
     *
     * @return array{tenant_id: string|null, variant_id: string|null}|null
     */
    public function resolve(string $reportId, string $action, ?string $by): ?array;

    /** Puts a blocked offer back. */
    public function unblock(string $tenantId, string $variantId): void;
}
