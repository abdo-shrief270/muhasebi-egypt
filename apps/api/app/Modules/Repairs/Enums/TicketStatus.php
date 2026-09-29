<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Enums;

enum TicketStatus: string
{
    case Received = 'received';
    case Diagnosing = 'diagnosing';
    case AwaitingApproval = 'awaiting_approval';
    case Repairing = 'repairing';
    case AwaitingPart = 'awaiting_part';
    case Ready = 'ready';
    case Rejected = 'rejected';
    case Delivered = 'delivered';

    public function label(): string
    {
        return match ($this) {
            self::Received => 'مستلم',
            self::Diagnosing => 'قيد الفحص',
            self::AwaitingApproval => 'مستني موافقة العميل',
            self::Repairing => 'قيد الإصلاح',
            self::AwaitingPart => 'مستني قطعة',
            self::Ready => 'جاهز للتسليم',
            self::Rejected => 'مرفوض / مش هيتصلح',
            self::Delivered => 'اتسلّم',
        };
    }

    /** Still in the shop. */
    public function isOpen(): bool
    {
        return $this !== self::Delivered;
    }

    /**
     * Where a ticket may go next by a status change. Delivery is its own step (money changes hands).
     *
     * @return list<self>
     */
    public function next(): array
    {
        return match ($this) {
            self::Received => [self::Diagnosing, self::AwaitingApproval, self::Repairing, self::Rejected],
            self::Diagnosing => [self::AwaitingApproval, self::Repairing, self::Rejected],
            self::AwaitingApproval => [self::Repairing, self::Rejected],
            self::Repairing => [self::AwaitingPart, self::Ready, self::Rejected],
            self::AwaitingPart => [self::Repairing, self::Ready, self::Rejected],
            self::Ready => [self::Repairing],
            self::Rejected => [self::Repairing],
            self::Delivered => [],
        };
    }

    /** Ready to hand back (repaired, or returned without repair). */
    public function canDeliver(): bool
    {
        return in_array($this, [self::Ready, self::Rejected], true);
    }
}
