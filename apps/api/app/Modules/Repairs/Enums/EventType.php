<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Enums;

enum EventType: string
{
    case Received = 'received';
    case Status = 'status';
    case Note = 'note';
    case Diagnosis = 'diagnosis';
    case Assigned = 'assigned';
    case PartAdded = 'part_added';
    case PartRemoved = 'part_removed';
    case PartPriced = 'part_priced';
    case PartRequest = 'part_request';
    case Payment = 'payment';
    case Delivered = 'delivered';
    case Outsourced = 'outsourced';
    case Partner = 'partner';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'استلام الجهاز',
            self::Status => 'تغيير الحالة',
            self::Note => 'ملاحظة',
            self::Diagnosis => 'التشخيص والتسعير',
            self::Assigned => 'اتسند لفني',
            self::PartAdded => 'قطعة اتركبت',
            self::PartRemoved => 'قطعة اتشالت',
            self::PartPriced => 'سعر قطعة اتغيّر',
            self::PartRequest => 'طلب قطعة',
            self::Payment => 'فلوس',
            self::Delivered => 'تسليم الجهاز',
            self::Outsourced => 'اتبعت لمحل شريك',
            self::Partner => 'المحل الشريك',
        };
    }
}
