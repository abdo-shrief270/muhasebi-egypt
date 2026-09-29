<?php

declare(strict_types=1);

namespace App\Modules\Identity\Contracts;

/** The signed-in shop's own profile, as other modules see it. */
interface ShopProfile
{
    /**
     * What the current shop does: one or more ShopType values (accessories, repair, phones…).
     *
     * @return list<string>
     */
    public function types(): array;
}
