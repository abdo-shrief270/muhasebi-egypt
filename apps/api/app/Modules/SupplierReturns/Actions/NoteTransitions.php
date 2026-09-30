<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Actions;

use App\Modules\SupplierReturns\Enums\NoteStatus;
use App\Modules\SupplierReturns\Models\BinItem;
use App\Modules\SupplierReturns\Models\ReturnNote;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Support\Facades\DB;

/** مستني يترجع → اتسلّم للمورد; or the note is cancelled and its units go back to the bin. */
final class NoteTransitions
{
    public function __construct(private readonly Auditor $audit) {}

    public function send(ReturnNote $note): ReturnNote
    {
        return DB::transaction(function () use ($note): ReturnNote {
            $note = ReturnNote::query()->lockForUpdate()->findOrFail($note->id);
            if ($note->status !== NoteStatus::Pending) {
                throw new DomainRuleException('الإذن ده مش مستني يترجع.', 'note_not_pending');
            }
            $note->update(['status' => NoteStatus::Sent, 'sent_at' => now()]);
            $this->audit->record('supplier_returns.sent', "سلّم إذن المرتجع {$note->reference()} لـ «{$note->source_name}»", $note);

            return $note;
        });
    }

    public function cancel(ReturnNote $note): ReturnNote
    {
        return DB::transaction(function () use ($note): ReturnNote {
            $note = ReturnNote::query()->lockForUpdate()->findOrFail($note->id);
            if (! $note->status->isOpen()) {
                throw new DomainRuleException('الإذن ده اتسوّى خلاص.', 'note_closed');
            }
            BinItem::query()->where('supplier_return_id', $note->id)->update(['supplier_return_id' => null, 'status' => BinItem::IN_BIN]);
            $note->update(['status' => NoteStatus::Cancelled, 'settled_at' => now()]);
            $this->audit->record('supplier_returns.cancelled', "لغى إذن المرتجع {$note->reference()} والقطع رجعت السلة", $note);

            return $note;
        });
    }
}
