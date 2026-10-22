<?php

declare(strict_types=1);

namespace App\Modules\Inquiries\Http\Controllers;

use App\Modules\Customers\Contracts\CustomerAccounts;
use App\Modules\Inquiries\Models\Inquiry;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

/**
 * «الاستعلامات»: what customers asked about. Recording one makes (or finds) the customer by phone,
 * like the POS picker; it ends answered, turned into a sale / repair ticket, or closed.
 */
final class InquiryController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly CurrentBranch $branch,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'due', 'answered', 'converted', 'closed', 'all'])],
            'customer_id' => ['nullable', 'uuid'],
            'q' => ['nullable', 'string', 'max:60'],
        ]);
        $status = $data['status'] ?? (isset($data['customer_id']) ? 'all' : 'open');
        $query = Inquiry::query()
            ->when(isset($data['customer_id']), fn (Builder $q) => $q->where('customer_id', $data['customer_id']))
            ->when($status === 'due', fn (Builder $q) => $q->where('status', 'open')->where('follow_up_at', '<=', now()))
            ->when(! in_array($status, ['due', 'all'], true), fn (Builder $q) => $q->where('status', $status))
            ->when(! empty($data['q']), function (Builder $q) use ($data): void {
                $term = trim((string) $data['q']);
                $digits = ltrim((string) preg_replace('/\D/', '', $term), '0');
                $q->where(fn (Builder $w) => $w->where('customer_name', 'ilike', "%{$term}%")->orWhere('text', 'ilike', "%{$term}%")
                    ->when(strlen($digits) >= 4, fn ($x) => $x->orWhere('customer_phone', 'like', "%{$digits}%")));
            });
        $page = $query->orderByRaw('follow_up_at is null')->orderBy('follow_up_at')->orderByDesc('created_at')->paginate(30);

        $counts = Inquiry::query()->select('status', DB::raw('count(*) as n'))->groupBy('status')->pluck('n', 'status');

        return response()->json([
            'data' => $page->getCollection()->map(fn (Inquiry $i) => $i->toArray())->all(),
            'meta' => [
                'page' => $page->currentPage(), 'last_page' => $page->lastPage(), 'total' => $page->total(),
                'counts' => [
                    ...array_map(fn () => 0, Inquiry::STATUSES), ...$counts->map(fn ($n) => (int) $n)->all(),
                    'due' => Inquiry::query()->where('status', 'open')->where('follow_up_at', '<=', now())->count(),
                ],
                'kinds' => array_map(fn ($k, $label) => ['value' => $k, 'label' => $label], array_keys(Inquiry::KINDS), Inquiry::KINDS),
            ],
        ]);
    }

    public function store(Request $request, CustomerAccounts $customers): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('inquiries.manage'), 403);
        $data = $request->validate([
            'customer_id' => ['nullable', 'uuid'],
            'customer_name' => ['required_without:customer_id', 'nullable', 'string', 'max:120'],
            'customer_phone' => ['required_without:customer_id', 'nullable', 'string', 'max:20'],
            'consent' => ['nullable', 'boolean'],
            'kind' => ['required', Rule::in(array_keys(Inquiry::KINDS))],
            'text' => ['required', 'string', 'max:1000'],
            'follow_up_at' => ['nullable', 'date', 'after:now'],
        ], [], ['text' => 'الاستفسار', 'customer_phone' => 'رقم التليفون', 'customer_name' => 'الاسم']);

        $inquiry = DB::transaction(function () use ($data, $customers, $request): Inquiry {
            $customer = isset($data['customer_id'])
                ? ($customers->find((string) $data['customer_id']) ?? throw new DomainRuleException('العميل مش موجود.', 'customer_not_found', 404))
                : $customers->findOrCreate((string) $data['customer_name'], (string) $data['customer_phone'], isset($data['consent']) ? (bool) $data['consent'] : null);
            $customers->touch($customer->id);

            return Inquiry::create([
                'tenant_id' => $this->tenant->idOrFail(),
                'branch_id' => $this->branch->id(),
                'customer_id' => $customer->id,
                'customer_name' => $customer->name,
                'customer_phone' => $customer->phone,
                'kind' => $data['kind'],
                'text' => $data['text'],
                'follow_up_at' => $data['follow_up_at'] ?? null,
                'created_by' => $request->user()?->getAuthIdentifier(),
                'created_by_name' => $request->user()?->getAttribute('name'),
            ]);
        });

        return response()->json(['data' => $inquiry->toArray()], 201);
    }

    /** Answered / turned into a sale or a ticket / closed, with what happened; or a new call-back time. */
    public function update(Request $request, Inquiry $inquiry): JsonResponse
    {
        abort_unless((bool) $request->user()?->can('inquiries.manage'), 403);
        $data = $request->validate([
            'status' => ['sometimes', Rule::in(array_keys(Inquiry::STATUSES))],
            'outcome' => ['sometimes', 'nullable', 'string', 'max:300'],
            'converted_to' => ['sometimes', 'nullable', Rule::in(['sale', 'repair'])],
            'follow_up_at' => ['sometimes', 'nullable', 'date'],
            'text' => ['sometimes', 'string', 'max:1000'],
        ]);
        if (array_key_exists('follow_up_at', $data)) {
            $data['reminded_at'] = null;
        }
        $inquiry->update($data);

        return response()->json(['data' => $inquiry->refresh()->toArray()]);
    }
}
