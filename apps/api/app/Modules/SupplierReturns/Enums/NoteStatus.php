<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Enums;

enum NoteStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Accepted = 'accepted';
    case PartiallyAccepted = 'partially_accepted';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'مستني يترجع',
            self::Sent => 'اتسلّم للمورد',
            self::Accepted => 'اتقبل',
            self::PartiallyAccepted => 'اتقبل جزء منه',
            self::Rejected => 'اترفض',
            self::Cancelled => 'اتلغى',
        };
    }

    public function isOpen(): bool
    {
        return $this === self::Pending || $this === self::Sent;
    }

    /** @return list<array{value: string, label: string}> */
    public static function options(): array
    {
        return array_map(fn (self $s): array => ['value' => $s->value, 'label' => $s->label()], self::cases());
    }
}
