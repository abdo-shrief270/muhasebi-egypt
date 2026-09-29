<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/**
 * What a shop does. A shop picks one or more (a shop that sells accessories and repairs picks
 * both); each optional module says which types it is for (ModuleManifest::$shopTypes).
 */
enum ShopType: string
{
    case Accessories = 'accessories';
    case Repair = 'repair';
    case Phones = 'phones';
    case Wholesale = 'wholesale';
    case Importer = 'importer';
    /** Before a shop could pick several types; stored rows are read as accessories + repair. */
    case AccessoriesAndRepair = 'accessories_repair';

    public function label(): string
    {
        return match ($this) {
            self::Accessories => 'إكسسوارات',
            self::Repair => 'صيانة',
            self::Phones => 'بيع موبايلات',
            self::Wholesale => 'جملة',
            self::Importer => 'مستورد',
            self::AccessoriesAndRepair => 'إكسسوارات + صيانة',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::Accessories => 'جرابات، سكرينات، شواحن وسماعات',
            self::Repair => 'استلام أجهزة وتصليحها',
            self::Phones => 'موبايلات جديدة ومستعملة',
            self::Wholesale => 'بتبيع للمحلات بالجملة',
            self::Importer => 'بتستورد بضاعة من برّه',
            self::AccessoriesAndRepair => 'إكسسوارات وصيانة',
        };
    }

    /** @return list<self> what a shop can pick */
    public static function selectable(): array
    {
        return [self::Accessories, self::Repair, self::Phones, self::Wholesale, self::Importer];
    }

    /** @return list<self> the selectable types this one stands for */
    public function parts(): array
    {
        return $this === self::AccessoriesAndRepair ? [self::Accessories, self::Repair] : [$this];
    }

    /**
     * @param  list<self>  $types
     */
    public static function labels(array $types): string
    {
        return implode(' + ', array_map(fn (self $t) => $t->label(), $types));
    }
}
