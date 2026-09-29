<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Actions;

use App\Modules\Repairs\Enums\EventType;
use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Support\Faults;
use App\Modules\Repairs\Support\Timeline;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/** The technician's side: diagnosis, labor, discount, who works on it, when it'll be ready, notes. */
final class UpdateTicketAction
{
    public function __construct(
        private readonly Timeline $timeline,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  array<string, mixed>  $data  only the keys present change
     */
    public function handle(RepairTicket $ticket, array $data): RepairTicket
    {
        if (! $ticket->status->isOpen()) {
            throw new DomainRuleException('الجهاز اتسلّم خلاص؛ مينفعش يتعدّل.', 'ticket_closed');
        }

        return DB::transaction(function () use ($ticket, $data): RepairTicket {
            $ticket = RepairTicket::query()->lockForUpdate()->findOrFail($ticket->id);

            $diagnosis = [];
            if (array_key_exists('diagnosed_fault_ids', $data)) {
                $ticket->diagnosed_faults = Faults::snapshot(array_map('intval', $data['diagnosed_fault_ids'] ?? []));
                $diagnosis[] = 'الأعطال: '.(implode('، ', array_column($ticket->diagnosed_faults, 'name')) ?: '—');
            }
            if (array_key_exists('diagnosis_note', $data)) {
                $ticket->diagnosis_note = $data['diagnosis_note'];
                if ($data['diagnosis_note']) {
                    $diagnosis[] = (string) $data['diagnosis_note'];
                }
            }
            if (array_key_exists('labor', $data) && (int) $data['labor'] !== $ticket->labor) {
                $ticket->labor = (int) $data['labor'];
                $diagnosis[] = 'المصنعية '.number_format($ticket->labor / 100, 2).' ج';
            }
            if (array_key_exists('discount', $data) && (int) $data['discount'] !== $ticket->discount) {
                $ticket->discount = (int) $data['discount'];
                $diagnosis[] = 'خصم '.number_format($ticket->discount / 100, 2).' ج';
                $this->audit->record('repairs.discounted', "خصم {$diagnosis[array_key_last($diagnosis)]} على التذكرة {$ticket->reference()}", $ticket, ['discount' => $ticket->discount]);
            }
            foreach (['estimate', 'expected_at', 'imei', 'color'] as $key) {
                if (array_key_exists($key, $data)) {
                    $ticket->{$key} = $data[$key];
                }
            }
            if (array_key_exists('technician_id', $data) && $data['technician_id'] !== $ticket->technician_id) {
                $ticket->technician_id = $data['technician_id'];
                $ticket->technician_name = $data['technician_name'] ?? null;
                $this->timeline->add($ticket, EventType::Assigned, $ticket->technician_name ?? 'من غير فني');
            }

            $ticket->recalculate();
            if ($ticket->total < $ticket->paid + $ticket->credit && $ticket->discount > 0) {
                // A discount can't make the shop owe back more than the deposit; keep it sane.
                $ticket->discount = max(0, $ticket->labor + $ticket->parts_total - $ticket->paid - $ticket->credit);
                $ticket->recalculate();
            }
            $ticket->save();

            if ($diagnosis !== []) {
                $this->timeline->add($ticket, EventType::Diagnosis, implode(' · ', $diagnosis));
            }
            if (! empty($data['note'])) {
                $this->timeline->add($ticket, EventType::Note, (string) $data['note']);
            }

            return $ticket;
        });
    }
}
