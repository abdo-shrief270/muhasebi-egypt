<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Controllers;

use App\Modules\Repairs\Models\RepairTicket;
use App\Modules\Repairs\Models\RepairWarranty;
use App\Modules\Repairs\Support\Warranties;
use App\Modules\Repairs\Support\WarrantySettings;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * «الضمانات»: the branch's warranty items (active / ending within a week / expired / voided /
 * came back), voiding one, and the shop's warranty wording and the labor's default days.
 */
final class WarrantyController
{
    public function __construct(
        private readonly CurrentBranch $branch,
        private readonly CurrentTenant $tenant,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['active', 'soon', 'expired', 'voided', 'claimed'])],
            'q' => ['nullable', 'string', 'max:60'],
        ]);
        $branchId = $this->branch->idOrFail();
        $now = now();
        $claimed = fn (Builder $q) => $q->whereExists(fn ($e) => $e->selectRaw('1')->from('repair_tickets as back')->whereColumn('back.warranty_of_id', 'repair_warranties.ticket_id'));

        $query = RepairWarranty::query()->with('ticket')->where('branch_id', $branchId);
        match ($data['status'] ?? 'active') {
            'active' => $query->whereNull('voided_at')->where('ends_at', '>', $now),
            'soon' => $query->whereNull('voided_at')->whereBetween('ends_at', [$now, $now->copy()->addDays(7)]),
            'expired' => $query->whereNull('voided_at')->where('ends_at', '<=', $now),
            'voided' => $query->whereNotNull('voided_at'),
            'claimed' => $claimed($query),
        };
        if (! empty($data['q'])) {
            $term = trim($data['q']);
            $number = (int) preg_replace('/\D/', '', $term);
            $digits = ltrim((string) preg_replace('/\D/', '', $term), '0');
            $query->whereHas('ticket', fn (Builder $t) => $t->where(fn (Builder $w) => $w
                ->where('customer_name', 'ilike', "%{$term}%")
                ->orWhere('device_name', 'ilike', "%{$term}%")
                ->when(strlen($digits) >= 4, fn ($x) => $x->orWhere('customer_phone', 'like', "%{$digits}%")->orWhere('imei', 'like', "%{$digits}%"))
                ->when($number > 0, fn ($x) => $x->orWhere('number', $number))));
        }
        $status = $data['status'] ?? 'active';
        $page = $query->orderBy('ends_at', in_array($status, ['expired', 'voided', 'claimed'], true) ? 'desc' : 'asc')->paginate(30);
        $backTickets = RepairTicket::query()->whereIn('warranty_of_id', $page->getCollection()->pluck('ticket_id')->unique()->all())->pluck('id', 'warranty_of_id');

        $monthStart = $now->copy()->startOfMonth();
        $summary = [
            'active' => RepairWarranty::query()->where('branch_id', $branchId)->whereNull('voided_at')->where('ends_at', '>', $now)->distinct()->count('ticket_id'),
            'soon' => RepairWarranty::query()->where('branch_id', $branchId)->whereNull('voided_at')->whereBetween('ends_at', [$now, $now->copy()->addDays(7)])->distinct()->count('ticket_id'),
            'claims_month' => RepairTicket::query()->where('branch_id', $branchId)->whereNotNull('warranty_of_id')->where('received_at', '>=', $monthStart)->count(),
            // What coming back cost this month: the parts (and partner work) of returns delivered.
            'claims_cost_month' => (int) RepairTicket::query()->where('branch_id', $branchId)->whereNotNull('warranty_of_id')->where('delivered_at', '>=', $monthStart)->sum(DB::raw('parts_cost + outsource_cost')),
        ];

        return response()->json([
            'data' => $page->getCollection()->map(fn (RepairWarranty $w) => [
                ...$w->toPublicArray(),
                'ticket_id' => $w->ticket_id,
                'reference' => $w->ticket?->reference(),
                'customer_id' => $w->customer_id,
                'customer_name' => $w->ticket?->customer_name,
                'customer_phone' => $w->ticket?->customer_phone,
                'device_name' => $w->ticket?->device_name,
                'public_token' => $w->ticket?->public_token,
                'came_back_ticket_id' => $backTickets[$w->ticket_id] ?? null,
            ])->all(),
            'meta' => ['page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(), 'summary' => $summary],
        ]);
    }

    /** Ends an item before its time (repairs.settings), with the reason. */
    public function void(Request $request, RepairWarranty $warranty, Auditor $audit): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('repairs.settings'), 403);
        $data = $request->validate(['reason' => ['required', 'string', 'max:200']], [], ['reason' => 'السبب']);

        DB::transaction(function () use ($warranty, $data, $request, $audit): void {
            $warranty->update(['voided_at' => now(), 'void_reason' => $data['reason'], 'voided_by_name' => $request->user()?->getAttribute('name')]);
            $ticket = RepairTicket::query()->lockForUpdate()->findOrFail($warranty->ticket_id);
            Warranties::sync($ticket);
            $ticket->save();
            $audit->record('repairs.warranty_voided', "لغى ضمان «{$warranty->name}» في {$ticket->reference()}: {$data['reason']}", $ticket);
        });

        return response()->json(['data' => $warranty->refresh()->toPublicArray()]);
    }

    public function settings(): JsonResponse
    {
        return response()->json(['data' => WarrantySettings::get($this->tenant->idOrFail())]);
    }

    public function saveSettings(Request $request, Auditor $audit): JsonResponse
    {
        $data = $request->validate([
            'warranty_terms' => ['nullable', 'string', 'max:2000'],
            'labor_warranty_days' => ['required', 'integer', 'min:0', 'max:730'],
        ], [], ['warranty_terms' => 'شروط الضمان', 'labor_warranty_days' => 'ضمان المصنعية']);
        WarrantySettings::save($this->tenant->idOrFail(), ['warranty_terms' => $data['warranty_terms'] ?? null, 'labor_warranty_days' => (int) $data['labor_warranty_days']]);
        $audit->record('repairs.warranty_settings', 'عدّل شروط الضمان وضمان المصنعية', tenantId: $this->tenant->idOrFail());

        return $this->settings();
    }
}
