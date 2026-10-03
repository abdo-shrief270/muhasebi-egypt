<?php

declare(strict_types=1);

namespace App\Modules\Imports\Support;

use App\Modules\Imports\Models\ImportContact;
use App\Modules\Imports\Models\ImportContactTransaction;
use Illuminate\Contracts\Auth\Factory as Auth;

/**
 * Posts to an import contact's statement under a row lock, keeping the balance in step. Call
 * inside the caller's transaction.
 */
final class ContactLedger
{
    public function __construct(private readonly Auth $auth) {}

    /** @param  int  $amount  piasters; + the shop owes more (goods, a cost), - less (a payment, a claim) */
    public function post(string $contactId, string $type, int $amount, ?string $refType = null, ?string $refId = null, ?string $note = null): void
    {
        if ($amount === 0) {
            return;
        }
        $contact = ImportContact::query()->lockForUpdate()->findOrFail($contactId);
        $contact->balance += $amount;
        $contact->save();

        ImportContactTransaction::create([
            'tenant_id' => $contact->tenant_id,
            'contact_id' => $contact->id,
            'type' => $type,
            'amount' => $amount,
            'balance_after' => $contact->balance,
            'ref_type' => $refType,
            'ref_id' => $refId,
            'note' => $note !== null ? mb_substr($note, 0, 255) : null,
            'user_name' => $this->auth->guard('sanctum')->user()?->getAttribute('name'),
            'created_at' => now(),
        ]);
    }
}
