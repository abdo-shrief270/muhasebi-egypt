<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Http\Controllers;

use App\Modules\OnlineStore\Actions\RepairBookingActions;
use App\Modules\OnlineStore\Models\OnlineStore;
use App\Modules\OnlineStore\Models\RepairBooking;
use App\Support\Time\ShopDay;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;

/** Repairs booked on the store: the customer's form, and the shop's list (online_store.orders). */
final class RepairBookingController
{
    public function __construct(private readonly RepairBookingActions $actions) {}

    public function book(Request $request): JsonResponse
    {
        $today = ShopDay::today();
        $data = $request->validate([
            'id' => ['nullable', 'uuid'],
            'name' => ['required', 'string', 'min:2', 'max:120'],
            'phone' => ['required', 'phone:EG,mobile'],
            'device' => ['required', 'string', 'min:2', 'max:120'],
            'problem' => ['required', 'string', 'min:3', 'max:1000'],
            'preferred_on' => ['nullable', 'date', 'after_or_equal:'.$today->toDateString(), 'before_or_equal:'.$today->addDays(30)->toDateString()],
            'consent' => ['nullable', 'boolean'],
            // Honeypot: a field people never see; bots fill it.
            'website' => ['nullable', 'max:0'],
        ], [], ['name' => 'الاسم', 'phone' => 'رقم الموبايل', 'device' => 'الجهاز', 'problem' => 'المشكلة', 'preferred_on' => 'اليوم']);

        /** @var OnlineStore $store */
        $store = $request->attributes->get('online_store');
        $booking = $this->actions->book(
            $store,
            $data['id'] ?? null,
            trim($data['name']),
            (new PhoneNumber($data['phone'], 'EG'))->formatE164(),
            trim($data['device']),
            trim($data['problem']),
            $data['preferred_on'] ?? null,
            (bool) ($data['consent'] ?? false),
        );

        return response()->json(['data' => ['reference' => $booking->reference(), 'preferred_on' => $booking->preferred_on?->toDateString()]], 201)
            ->header('Cache-Control', 'no-store');
    }

    public function index(Request $request): JsonResponse
    {
        $data = $request->validate(['status' => ['nullable', Rule::in(['open', ...array_keys(RepairBooking::STATUSES)])]]);
        $status = $data['status'] ?? 'open';
        $page = RepairBooking::query()
            ->when($status === 'open', fn ($q) => $q->whereIn('status', RepairBooking::OPEN))
            ->when($status !== 'open', fn ($q) => $q->where('status', $status))
            ->orderByDesc('created_at')
            ->paginate(30);

        return response()->json([
            'data' => collect($page->items())->map(fn (RepairBooking $b) => $b->toApi())->all(),
            'meta' => ['total' => $page->total(), 'last_page' => $page->lastPage(), 'new' => RepairBooking::query()->where('status', 'new')->count()],
        ]);
    }

    public function show(RepairBooking $booking): JsonResponse
    {
        return response()->json(['data' => $booking->toApi()]);
    }

    public function move(Request $request, RepairBooking $booking): JsonResponse
    {
        $data = $request->validate([
            'status' => ['required', 'in:contacted,cancelled'],
            'reason' => ['nullable', 'string', 'max:255'],
        ]);

        return response()->json(['data' => $this->actions->move($booking, $data['status'], $data['reason'] ?? null)->toApi()]);
    }
}
