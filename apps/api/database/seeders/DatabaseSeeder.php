<?php

namespace Database\Seeders;

use App\Modules\Identity\Actions\RegisterTenantAction;
use App\Modules\Identity\Data\RegisterTenantData;
use App\Modules\Identity\Enums\ShopType;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * A demo shop to log in with: +201000000000 / password
     */
    public function run(RegisterTenantAction $register): void
    {
        $register->handle(new RegisterTenantData(
            shopName: 'محل التجربة',
            shopType: ShopType::AccessoriesAndRepair,
            ownerName: 'صاحب المحل',
            phone: '+201000000000',
            email: 'owner@example.com',
            password: 'password',
            branchName: 'الفرع الرئيسي',
        ));
    }
}
