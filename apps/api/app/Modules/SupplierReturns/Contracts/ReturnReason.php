<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Contracts;

/** Why a unit goes back to its supplier (the returns bin's fixed list). */
enum ReturnReason: string
{
    case ManufacturingDefect = 'defect';
    case NotWorking = 'not_working';
    case BrokenInShipping = 'shipping_damage';
    case WrongItem = 'wrong_item';
    case WarrantyExpired = 'warranty_expired';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::ManufacturingDefect => 'عيب صناعة',
            self::NotWorking => 'مش شغال',
            self::BrokenInShipping => 'مكسور في الشحن',
            self::WrongItem => 'غلط في الطلب',
            self::WarrantyExpired => 'انتهى ضمانه',
            self::Other => 'سبب تاني',
        };
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(fn (self $r): array => ['value' => $r->value, 'label' => $r->label()], self::cases());
    }
}
