<?php

declare(strict_types=1);

namespace App\Modules\Messaging;

use App\Modules\Messaging\Contracts\MessageHistory;
use App\Modules\Messaging\Models\MessageLog;
use Illuminate\Support\Carbon;

final class MessageHistoryService implements MessageHistory
{
    public function lastSent(string $subjectType, array $subjectIds, string $template): array
    {
        if ($subjectIds === []) {
            return [];
        }

        return MessageLog::query()
            ->where('subject_type', $subjectType)
            ->whereIn('subject_id', $subjectIds)
            ->where('template', $template)
            ->groupBy('subject_id')
            ->selectRaw('subject_id, max(created_at) as last_at')
            ->get()
            ->mapWithKeys(fn (MessageLog $l) => [(string) $l->subject_id => Carbon::parse((string) $l->getAttribute('last_at'))->toIso8601String()])
            ->all();
    }
}
