<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use App\Modules\Identity\Enums\ShopType;

final readonly class RegisterTenantData
{
    public function __construct(
        public string $shopName,
        /** @var list<ShopType> one or more */
        public array $shopTypes,
        public string $ownerName,
        public string $phone,
        public ?string $email,
        public string $password,
        public string $branchName,
        /** The invite code of the shop that brought it (another shop's code), if any. */
        public ?string $referralCode = null,
        /** @var array<string, string> where the owner came from: source, medium, campaign, content, term */
        public array $acquisition = [],
    ) {}
}
