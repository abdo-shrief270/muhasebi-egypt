<?php

declare(strict_types=1);

namespace App\Modules\Services\Enums;

enum AccountKind: string
{
    /** A mobile wallet (Vodafone Cash…) or InstaPay: customers deposit and withdraw through it. */
    case Wallet = 'wallet';
    /** Electronic airtime balance (رصيد / شحن فكة), bought from a distributor, topped up onto customers' lines. */
    case Airtime = 'airtime';

    public function label(): string
    {
        return match ($this) {
            self::Wallet => 'محفظة',
            self::Airtime => 'رصيد شحن',
        };
    }

    /**
     * The customer operations this kind of account takes.
     *
     * @return list<OperationType>
     */
    public function operations(): array
    {
        return match ($this) {
            self::Wallet => [OperationType::Deposit, OperationType::Withdraw],
            self::Airtime => [OperationType::Topup],
        };
    }
}
