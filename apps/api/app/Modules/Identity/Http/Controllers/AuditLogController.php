<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Resources\AuditEntryResource;
use App\Support\Audit\AuditEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class AuditLogController
{
    public function index(Request $request): AnonymousResourceCollection
    {
        return AuditEntryResource::collection(
            AuditEntry::query()
                ->when($request->query('user_id'), fn ($q, $userId) => $q->where('user_id', $userId))
                ->when($request->query('action'), fn ($q, $action) => $q->where('action', 'like', $action.'%'))
                ->orderByDesc('id')
                ->cursorPaginate(50),
        );
    }
}
