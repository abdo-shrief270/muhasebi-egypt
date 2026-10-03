<?php

declare(strict_types=1);

namespace App\Modules\Imports\Actions;

use App\Modules\Imports\Models\ImportContact;
use App\Modules\Imports\Models\ImportPayment;
use App\Modules\Imports\Models\ImportShipment;
use App\Modules\Imports\Support\ContactLedger;
use App\Modules\Imports\Support\ImportFiles;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Money sent to an import contact (bank, money-transfer company, through an agent…), in EGP. It
 * comes off their statement; a mistake is reversed (a counter line), never edited.
 */
final class PaymentActions
{
    public function __construct(
        private readonly ContactLedger $ledger,
        private readonly ImportFiles $files,
        private readonly Auditor $audit,
    ) {}

    /** @param  array{contact_id: string, shipment_id: ?string, amount: int, method: string, paid_on: string, received_by: ?string, reference: ?string, note: ?string}  $data */
    public function pay(Authenticatable $user, string $tenantId, array $data, ?UploadedFile $proof): ImportPayment
    {
        $contact = ImportContact::query()->find($data['contact_id']) ?? throw new DomainRuleException('الجهة دي مش موجودة.', 'contact_not_found', 404);
        if ($data['shipment_id'] !== null && ! ImportShipment::query()->whereKey($data['shipment_id'])->exists()) {
            throw new DomainRuleException('الشحنة دي مش موجودة.', 'shipment_not_found', 404);
        }
        $id = (string) Str::uuid7();
        $proofName = $proof !== null ? $this->files->putProof($tenantId, $id, $proof) : null;

        return DB::transaction(function () use ($user, $tenantId, $data, $contact, $id, $proofName): ImportPayment {
            $payment = new ImportPayment([...$data, 'tenant_id' => $tenantId, 'proof' => $proofName, 'user_name' => $user->getAttribute('name')]);
            $payment->id = $id;
            $payment->save();
            $this->ledger->post($contact->id, 'payment', -$payment->amount, 'import_payment', $payment->id, ImportPayment::METHODS[$payment->method].($payment->reference ? " ({$payment->reference})" : ''));
            $this->audit->record('imports.payment', 'دفع '.number_format($payment->amount / 100, 2)." ج لـ «{$contact->name}»", $payment);

            return $payment;
        });
    }

    public function reverse(ImportPayment $payment, string $reason): ImportPayment
    {
        return DB::transaction(function () use ($payment, $reason): ImportPayment {
            $payment = ImportPayment::query()->lockForUpdate()->findOrFail($payment->id);
            if ($payment->reversed_at !== null) {
                throw new DomainRuleException('الدفعة دي اتلغت قبل كده.', 'payment_reversed');
            }
            $payment->update(['reversed_at' => now()]);
            $this->ledger->post($payment->contact_id, 'reversal', $payment->amount, 'import_payment', $payment->id, "إلغاء دفعة: {$reason}");
            $this->audit->record('imports.payment_reversed', 'لغى دفعة '.number_format($payment->amount / 100, 2)." ج: {$reason}", $payment);

            return $payment;
        });
    }
}
