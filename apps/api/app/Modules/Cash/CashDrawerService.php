<?php

declare(strict_types=1);

namespace App\Modules\Cash;

use App\Modules\Cash\Contracts\CashDrawer;
use App\Modules\Cash\Contracts\DrawerEntry;
use App\Modules\Cash\Enums\Method;
use App\Modules\Cash\Enums\MovementType;
use App\Modules\Cash\Models\CashMovement;
use App\Modules\Cash\Models\CashShift;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Contracts\Auth\Factory as Auth;

final class CashDrawerService implements CashDrawer
{
    public function __construct(
        private readonly Auth $auth,
        private readonly CurrentTenant $tenant,
    ) {}

    public function record(
        string $branchId,
        DrawerEntry $entry,
        string $method,
        int $amount,
        string $refType,
        string $refId,
        ?string $note = null,
        bool $requireShift = false,
    ): void {
        if ($amount === 0) {
            return;
        }
        $method = Method::from($method);
        $shift = $this->openShift($branchId);
        if ($shift === null && ($requireShift || $method === Method::Cash)) {
            throw new DomainRuleException('افتح وردية الأول عشان الفلوس تتسجل في درجك.', 'shift_not_open', 409);
        }

        $user = $this->auth->guard('sanctum')->user();
        CashMovement::create([
            'tenant_id' => $this->tenant->idOrFail(),
            'branch_id' => $branchId,
            'shift_id' => $shift?->id,
            'type' => MovementType::from($entry->value),
            'method' => $method,
            'amount' => $amount,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'note' => $note,
            'user_id' => $user?->getAuthIdentifier(),
            'user_name' => $user?->getAttribute('name'),
            'created_at' => now(),
        ]);
    }

    public function redactNotes(string $refType, array $refIds, string $note): void
    {
        if ($refIds === []) {
            return;
        }

        // A query update: movements are append-only for the app (the model refuses updates); only the note changes.
        CashMovement::query()->where('ref_type', $refType)->whereIn('ref_id', $refIds)->whereNotNull('note')->update(['note' => $note]);
    }

    public function hasOpenShift(string $branchId): bool
    {
        return $this->openShift($branchId) !== null;
    }

    private function openShift(string $branchId): ?CashShift
    {
        $userId = $this->auth->guard('sanctum')->id();

        return $userId === null ? null : CashShift::query()
            ->where('branch_id', $branchId)
            ->where('user_id', $userId)
            ->whereNull('closed_at')
            // Sales share the row; closing the shift takes it exclusively, so nothing lands after the count.
            ->sharedLock()
            ->first();
    }
}
