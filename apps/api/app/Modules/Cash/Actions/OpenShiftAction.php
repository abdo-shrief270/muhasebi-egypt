<?php

declare(strict_types=1);

namespace App\Modules\Cash\Actions;

use App\Modules\Cash\Models\CashShift;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

final class OpenShiftAction
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly Auditor $audit,
    ) {}

    public function handle(string $tenantId, string $branchId, Authenticatable $user, int $openingCash, ?string $note): CashShift
    {
        try {
            return DB::transaction(function () use ($tenantId, $branchId, $user, $openingCash, $note): CashShift {
                $shift = CashShift::create([
                    'tenant_id' => $tenantId,
                    'branch_id' => $branchId,
                    'number' => $this->numbers->next($tenantId, 'cash_shift'),
                    'user_id' => $user->getAuthIdentifier(),
                    'user_name' => (string) $user->getAttribute('name'),
                    'opening_cash' => $openingCash,
                    'opened_at' => now(),
                    'note' => $note,
                ]);
                $this->audit->record('cash.shift_opened', "فتح الوردية {$shift->reference()} بـ ".number_format($openingCash / 100, 2).' ج في الدرج', $shift);

                return $shift;
            });
        } catch (UniqueConstraintViolationException) {
            throw new DomainRuleException('عندك وردية مفتوحة في الفرع ده بالفعل.', 'shift_already_open', 409);
        }
    }
}
