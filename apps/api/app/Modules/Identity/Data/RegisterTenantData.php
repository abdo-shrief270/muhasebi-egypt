<?php

declare(strict_types=1);

namespace App\Modules\Identity\Data;

use App\Modules\Identity\Enums\ShopType;

final readonly class RegisterTenantData
{
    public function __construct(
        public string $shopName,
        public ShopType $shopType,
        public string $ownerName,
        public string $phone,
        public ?string $email,
        public string $password,
        public string $branchName,
    ) {}
}
