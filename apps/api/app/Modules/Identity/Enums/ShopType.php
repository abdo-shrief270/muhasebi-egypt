<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

enum ShopType: string
{
    case Accessories = 'accessories';
    case Repair = 'repair';
    case AccessoriesAndRepair = 'accessories_repair';
    case Importer = 'importer';
    case Wholesale = 'wholesale';

    public function label(): string
    {
        return match ($this) {
            self::Accessories => 'إكسسوارات',
            self::Repair => 'صيانة',
            self::AccessoriesAndRepair => 'إكسسوارات + صيانة',
            self::Importer => 'مستورد',
            self::Wholesale => 'جملة',
        };
    }

    /**
     * Optional modules suggested (and put on trial) when a shop of this type registers.
     *
     * @return list<string>
     */
    public function recommendedModules(): array
    {
        return match ($this) {
            self::Accessories => [],
            self::Repair => ['repairs', 'supplier_returns', 'used_devices'],
            self::AccessoriesAndRepair => ['repairs', 'supplier_returns', 'used_devices', 'services'],
            self::Importer => ['imports', 'supplier_returns', 'owner_app'],
            self::Wholesale => ['supplier_returns', 'installments'],
        };
    }
}
