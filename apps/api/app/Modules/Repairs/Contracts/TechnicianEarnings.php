<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Contracts;

use Carbon\CarbonInterface;

/** What technicians earned from repairs in a period, for payroll (piasters). */
interface TechnicianEarnings
{
    /**
     * Per technician: devices delivered repaired in the period and their commission, and what
     * warranty returns received in the period take back (the first ticket's commission, when that
     * technician's rule says so).
     *
     * @param  list<string>  $userIds
     * @return array<string, array{tickets: int, commission: int, clawback: int}>
     */
    public function between(array $userIds, CarbonInterface $from, CarbonInterface $to): array;

    /**
     * The tickets behind one technician's figures, newest first.
     *
     * @return list<array{ticket_id: string, reference: string, device: string, delivered_at: string|null, labor: int, parts: int, rule: string|null, amount: int, clawback: bool}>
     */
    public function lines(string $userId, CarbonInterface $from, CarbonInterface $to): array;
}
