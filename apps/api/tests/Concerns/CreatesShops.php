<?php

namespace Tests\Concerns;

use App\Modules\Identity\Actions\RegisterTenantAction;
use App\Modules\Identity\Data\RegisterTenantData;
use App\Modules\Identity\Enums\ShopType;
use App\Modules\Identity\Models\User;
use Laravel\Sanctum\Sanctum;

trait CreatesShops
{
    private int $shopCounter = 0;

    protected function registerShop(ShopType $type = ShopType::Accessories, ?string $phone = null): User
    {
        $this->shopCounter++;

        return app(RegisterTenantAction::class)->handle(new RegisterTenantData(
            shopName: "محل {$this->shopCounter}",
            shopTypes: $type->parts(),
            ownerName: 'المالك',
            phone: $phone ?? sprintf('+20100000%04d', $this->shopCounter), // unique within a test (each test starts on an empty database)
            email: null,
            password: 'password',
            branchName: 'الفرع الرئيسي',
        ));
    }

    protected function actingAsOwnerOf(ShopType $type = ShopType::Accessories): User
    {
        $owner = $this->registerShop($type);
        Sanctum::actingAs($owner);

        return $owner;
    }
}
