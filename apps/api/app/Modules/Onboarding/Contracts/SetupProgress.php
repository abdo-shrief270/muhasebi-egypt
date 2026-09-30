<?php

declare(strict_types=1);

namespace App\Modules\Onboarding\Contracts;

/**
 * How far shops got through «ابدأ من هنا» (for the platform admins: which beta shops are stuck).
 */
interface SetupProgress
{
    /**
     * Shop-wide (not per user): every step that applies to the shop.
     *
     * @param  list<string>  $tenantIds
     * @return array<string, array{done: int, total: int, missing: list<string>}> missing = titles of the steps not done yet
     */
    public function progress(array $tenantIds): array;
}
