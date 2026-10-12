<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Contracts;

/**
 * What customers can trust about a shop: its rating from delivered orders, and how reliably it
 * answers marketplace orders in time. Read across shops («سوق محاسبي», the platform admins).
 */
interface ShopReputation
{
    /** Minutes a shop has to answer a marketplace order before it's cancelled for it. */
    public function answerMinutes(): int;

    /**
     * @param  list<string>|null  $tenantIds  null = every shop with reviews or marketplace orders
     * @return array<string, array{rating: float|null, reviews: int, response: float|null}> tenant id => reputation (response 0..1, null = too few orders)
     */
    public function scores(?array $tenantIds = null): array;

    /** @return list<array{rating: int, comment: string|null, name: string, at: string}> a shop's latest visible reviews */
    public function latest(string $tenantId, int $limit = 10): array;

    /**
     * For the platform admins: newest first.
     *
     * @return list<array<string, mixed>>
     */
    public function adminReviews(?bool $hidden = null, int $limit = 100): array;

    public function setHidden(string $reviewId, bool $hidden): ?string;
}
