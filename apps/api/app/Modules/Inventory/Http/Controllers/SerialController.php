<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Http\Controllers;

use App\Modules\Catalog\Contracts\VariantCatalog;
use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\Inventory\Models\SerialEvent;
use App\Modules\Inventory\Models\SerialNumber;
use App\Support\Tenancy\CurrentBranch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Finds a unit by its IMEI / serial (the whole number or its last digits): what it is, where it
 * is now, and its story — bought from whom, sold on which invoice, returned.
 */
final class SerialController
{
    public function __construct(
        private readonly SerialRegistry $registry,
        private readonly VariantCatalog $catalog,
        private readonly BranchDirectory $branches,
        private readonly CurrentBranch $branch,
    ) {}

    public function __invoke(Request $request): JsonResponse
    {
        $user = $request->user();
        abort_unless($user !== null && ($user->can('inventory.view') || $user->can('sales.sell') || $user->can('sales.refund')), 403);

        $q = $this->registry->normalize([(string) $request->query('q', '')])[0];
        if (strlen($q) < 4) {
            return response()->json(['data' => []]);
        }

        $rows = SerialNumber::query()
            ->where(fn ($w) => $w->where('serial', $q)->orWhere('serial', 'like', '%'.addcslashes($q, '%_\\')))
            ->when($request->boolean('in_stock'), fn ($w) => $w->where('status', SerialNumber::IN_STOCK)->where('branch_id', $this->branch->idOrFail()))
            ->with('events')
            ->orderByRaw('serial = ? desc', [$q])
            ->limit(10)
            ->get();

        $variants = $this->catalog->find($rows->pluck('variant_id')->unique()->values()->all());
        $branchNames = $this->branches->accessibleBranches($user);

        return response()->json(['data' => $rows->map(fn (SerialNumber $s): array => [
            'serial' => $s->serial,
            'status' => $s->status,
            'status_label' => match ($s->status) {
                SerialNumber::IN_STOCK => 'في المخزن',
                SerialNumber::DAMAGED => 'مرتجع تالف',
                default => 'خرج',
            },
            'variant' => ($variants[$s->variant_id] ?? null)?->toArray(),
            'branch_id' => $s->branch_id,
            'branch_name' => $branchNames[$s->branch_id] ?? null,
            'here' => $s->status === SerialNumber::IN_STOCK && $s->branch_id === $this->branch->id(),
            'events' => $s->events->map(fn (SerialEvent $e): array => [
                'type' => $e->type->value,
                'type_label' => $e->type->label(),
                'ref_type' => $e->ref_type,
                'ref_id' => $e->ref_id,
                'note' => $e->note,
                'user_name' => $e->user_name,
                'branch_name' => $branchNames[$e->branch_id] ?? null,
                'created_at' => $e->created_at->toIso8601String(),
            ])->all(),
        ])->all()]);
    }
}
