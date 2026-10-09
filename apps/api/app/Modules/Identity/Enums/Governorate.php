<?php

declare(strict_types=1);

namespace App\Modules\Identity\Enums;

/** Egypt's 27 governorates: where a branch is (the marketplace filters and sorts by it). */
enum Governorate: string
{
    case Cairo = 'cairo';
    case Giza = 'giza';
    case Alexandria = 'alexandria';
    case Qalyubia = 'qalyubia';
    case Sharqia = 'sharqia';
    case Dakahlia = 'dakahlia';
    case Gharbia = 'gharbia';
    case Monufia = 'monufia';
    case Beheira = 'beheira';
    case KafrElSheikh = 'kafr_el_sheikh';
    case Damietta = 'damietta';
    case PortSaid = 'port_said';
    case Ismailia = 'ismailia';
    case Suez = 'suez';
    case Faiyum = 'faiyum';
    case BeniSuef = 'beni_suef';
    case Minya = 'minya';
    case Asyut = 'asyut';
    case Sohag = 'sohag';
    case Qena = 'qena';
    case Luxor = 'luxor';
    case Aswan = 'aswan';
    case RedSea = 'red_sea';
    case NewValley = 'new_valley';
    case Matrouh = 'matrouh';
    case NorthSinai = 'north_sinai';
    case SouthSinai = 'south_sinai';

    public function label(): string
    {
        return match ($this) {
            self::Cairo => 'القاهرة',
            self::Giza => 'الجيزة',
            self::Alexandria => 'الإسكندرية',
            self::Qalyubia => 'القليوبية',
            self::Sharqia => 'الشرقية',
            self::Dakahlia => 'الدقهلية',
            self::Gharbia => 'الغربية',
            self::Monufia => 'المنوفية',
            self::Beheira => 'البحيرة',
            self::KafrElSheikh => 'كفر الشيخ',
            self::Damietta => 'دمياط',
            self::PortSaid => 'بورسعيد',
            self::Ismailia => 'الإسماعيلية',
            self::Suez => 'السويس',
            self::Faiyum => 'الفيوم',
            self::BeniSuef => 'بني سويف',
            self::Minya => 'المنيا',
            self::Asyut => 'أسيوط',
            self::Sohag => 'سوهاج',
            self::Qena => 'قنا',
            self::Luxor => 'الأقصر',
            self::Aswan => 'أسوان',
            self::RedSea => 'البحر الأحمر',
            self::NewValley => 'الوادي الجديد',
            self::Matrouh => 'مطروح',
            self::NorthSinai => 'شمال سيناء',
            self::SouthSinai => 'جنوب سيناء',
        };
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $g) => ['value' => $g->value, 'label' => $g->label()], self::cases());
    }
}
