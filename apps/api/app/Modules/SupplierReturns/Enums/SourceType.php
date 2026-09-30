<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Enums;

/** Where returned units go back to. */
enum SourceType: string
{
    case Supplier = 'supplier';
    /** A partner shop the goods were ordered from (الطلبات بين المحلات). */
    case Shop = 'shop';

    public function label(): string
    {
        return match ($this) {
            self::Supplier => 'مورد',
            self::Shop => 'محل شريك',
        };
    }
}
