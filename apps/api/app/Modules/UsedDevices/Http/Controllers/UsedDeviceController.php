<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Http\Controllers;

use App\Modules\Catalog\Contracts\DeviceModelSummary;
use App\Modules\Catalog\Contracts\UsedDeviceCatalog;
use App\Modules\Inventory\Contracts\SerialRegistry;
use App\Modules\UsedDevices\Actions\BuyDeviceAction;
use App\Modules\UsedDevices\Actions\UpdateDeviceAction;
use App\Modules\UsedDevices\Enums\Grade;
use App\Modules\UsedDevices\Enums\PaymentMethod;
use App\Modules\UsedDevices\Http\Requests\BuyDeviceRequest;
use App\Modules\UsedDevices\Http\Resources\UsedDeviceResource;
use App\Modules\UsedDevices\Models\UsedDevice;
use App\Modules\UsedDevices\Models\UsedDevicePhoto;
use App\Modules\UsedDevices\Models\UsedDeviceSeller;
use App\Modules\UsedDevices\Support\Checklist;
use App\Modules\UsedDevices\Support\Imei;
use App\Modules\UsedDevices\Support\NationalId;
use App\Modules\UsedDevices\Support\PhotoStore;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentBranch;
use App\Support\Tenancy\CurrentTenant;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

final class UsedDeviceController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly CurrentBranch $branch,
    ) {}

    /** This branch's devices. ?status=in_stock|sold|all ?grade= ?device_model_id= ?q= (model, IMEI, UD number) ?from= ?to= (bought, Cairo days) */
    public function index(Request $request): JsonResponse
    {
        $request->validate([
            'status' => ['nullable', Rule::in(['in_stock', 'sold', 'all'])],
            'grade' => ['nullable', Rule::enum(Grade::class)],
            'device_model_id' => ['nullable', 'integer'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);
        $status = (string) $request->query('status', 'in_stock');

        $devices = UsedDevice::query()
            ->with('seller')
            ->where('branch_id', $this->branch->idOrFail())
            ->when($status === 'in_stock', fn (Builder $q) => $q->where('status', UsedDevice::IN_STOCK))
            ->when($status === 'sold', fn (Builder $q) => $q->where('status', '!=', UsedDevice::IN_STOCK))
            ->when($request->filled('grade'), fn (Builder $q) => $q->where('grade', (string) $request->query('grade')))
            ->when($request->filled('device_model_id'), fn (Builder $q) => $q->where('device_model_id', $request->integer('device_model_id')))
            ->when($request->filled('from'), fn (Builder $q) => $q->where('bought_at', '>=', CarbonImmutable::parse((string) $request->query('from'), 'Africa/Cairo')->startOfDay()->utc()))
            ->when($request->filled('to'), fn (Builder $q) => $q->where('bought_at', '<=', CarbonImmutable::parse((string) $request->query('to'), 'Africa/Cairo')->endOfDay()->utc()))
            ->when($request->filled('q'), function (Builder $q) use ($request): void {
                $term = trim((string) $request->query('q'));
                $digits = Imei::normalize($term);
                $number = (int) preg_replace('/^UD-?/i', '', $term);
                $q->where(fn (Builder $w) => $w
                    ->where('model_name', 'ilike', '%'.addcslashes($term, '%_\\').'%')
                    ->when(strlen($digits) >= 4, fn (Builder $x) => $x->orWhere('imei', 'like', '%'.$digits.'%')->orWhere('imei2', 'like', '%'.$digits.'%'))
                    ->when($number > 0 && preg_match('/^(UD-?)?\d+$/i', $term), fn (Builder $x) => $x->orWhere('number', $number)));
            })
            ->orderByDesc($status === 'sold' ? 'sold_at' : 'bought_at')
            ->orderByDesc('number')
            ->paginate(30);

        $inStock = UsedDevice::query()->where('branch_id', $this->branch->idOrFail())->where('status', UsedDevice::IN_STOCK);
        $monthStart = CarbonImmutable::now('Africa/Cairo')->startOfMonth()->utc();
        $withCost = (bool) $request->user()?->can('products.view_cost');

        return response()->json([
            'data' => $devices->getCollection()->map(fn (UsedDevice $d) => new UsedDeviceResource($d)),
            'meta' => ['current_page' => $devices->currentPage(), 'last_page' => $devices->lastPage(), 'per_page' => $devices->perPage(), 'total' => $devices->total()],
            'summary' => [
                'in_stock' => (clone $inStock)->count(),
                'stock_asking' => (int) (clone $inStock)->sum('asking_price'),
                'stock_cost' => $withCost ? (int) (clone $inStock)->sum('purchase_price') : null,
                'bought_this_month' => UsedDevice::query()->where('branch_id', $this->branch->idOrFail())->where('bought_at', '>=', $monthStart)->count(),
                'sold_this_month' => UsedDevice::query()->where('branch_id', $this->branch->idOrFail())->where('status', UsedDevice::SOLD)->where('sold_at', '>=', $monthStart)->count(),
            ],
        ]);
    }

    public function options(Request $request): JsonResponse
    {
        return response()->json(['data' => [
            'grades' => array_map(fn (Grade $g) => ['value' => $g->value, 'label' => $g->label()], Grade::cases()),
            'checklist' => Checklist::items(),
            'payment_methods' => array_map(fn (PaymentMethod $m) => ['value' => $m->value, 'label' => $m->label()], PaymentMethod::cases()),
            'max_photo_kb' => BuyDeviceRequest::MAX_PHOTO_KB,
            'max_device_photos' => BuyDeviceRequest::MAX_DEVICE_PHOTOS,
            'can_view_seller' => (bool) $request->user()?->can('used_devices.view_seller'),
        ]]);
    }

    /** The shop's phone models (Catalog), for the buy form. */
    public function deviceModels(Request $request, UsedDeviceCatalog $catalog): JsonResponse
    {
        $q = trim((string) $request->query('q', ''));

        return response()->json(['data' => array_map(fn (DeviceModelSummary $m) => $m->toArray(), $catalog->deviceModels($q === '' ? null : $q))]);
    }

    /**
     * Before buying: is the IMEI valid, is that phone already in stock (refused), and was it here
     * before — sold by the shop and now bought back, or bought used before.
     */
    public function imeiCheck(Request $request, SerialRegistry $serials): JsonResponse
    {
        $imei = Imei::normalize((string) $request->query('imei', ''));
        $history = strlen($imei) >= 8 ? $serials->history($imei) : null;
        $previous = strlen($imei) >= 8
            ? UsedDevice::query()->where(fn (Builder $q) => $q->where('imei', $imei)->orWhere('imei2', $imei))->orderByDesc('bought_at')->get()
            : collect();

        return response()->json(['data' => [
            'imei' => $imei,
            'valid' => Imei::isValid($imei),
            'in_stock' => ($history['status'] ?? null) === 'in_stock',
            'sold_before' => collect($history['events'] ?? [])->contains(fn (array $e) => $e['type'] === 'sale'),
            'events' => $history['events'] ?? [],
            'previous' => $previous->map(fn (UsedDevice $d) => ['id' => $d->id, 'reference' => $d->reference(), 'title' => $d->title(), 'bought_at' => $d->bought_at->toIso8601String(), 'status' => $d->status])->values()->all(),
        ]]);
    }

    /**
     * Before buying: what the national ID says (birth date, gender, governorate) and whether this
     * person sold the shop devices before. Their name and phone only with used_devices.view_seller.
     */
    public function nationalIdCheck(Request $request): JsonResponse
    {
        [$id, $error] = NationalId::parse((string) $request->query('national_id', ''));
        if ($id === null) {
            return response()->json(['data' => ['valid' => false, 'error' => $error]]);
        }
        $seller = UsedDeviceSeller::query()->where('national_id_hash', $id->hash())->withCount('devices')->first();
        $withSeller = (bool) $request->user()?->can('used_devices.view_seller');

        return response()->json(['data' => [
            'valid' => true,
            ...$id->details(),
            'known' => $seller !== null,
            'devices_count' => (int) ($seller->devices_count ?? 0),
            'seller' => $seller !== null && $withSeller && ! $seller->isErased() ? ['id' => $seller->id, 'name' => $seller->name, 'phone' => $seller->phone] : null,
        ]]);
    }

    public function show(string $device): UsedDeviceResource
    {
        return new UsedDeviceResource(UsedDevice::query()->with(['seller', 'photos'])->where('branch_id', $this->branch->idOrFail())->findOrFail($device), detail: true);
    }

    public function store(BuyDeviceRequest $request, BuyDeviceAction $action): JsonResponse
    {
        $device = $action->handle($this->tenant->idOrFail(), $this->branch->idOrFail(), $request->seller(), $request->device(), $request->photos());

        // Whoever just typed the seller's details gets them back once, for the declaration they print now.
        return (new UsedDeviceResource($device->load(['seller', 'photos']), detail: true, showSeller: true))->response()->setStatusCode(201);
    }

    public function update(Request $request, string $device, UpdateDeviceAction $action): UsedDeviceResource
    {
        abort_unless((bool) $request->user()?->can('used_devices.manage'), 403);
        $data = $request->validate([
            'asking_price' => ['sometimes', 'integer', 'min:1', 'max:100000000000'],
            'notes' => ['sometimes', 'nullable', 'string', 'max:500'],
        ], attributes: ['asking_price' => 'سعر البيع']);
        $model = UsedDevice::query()->where('branch_id', $this->branch->idOrFail())->findOrFail($device);
        $action->handle($model, $data);

        return $this->show($model->id);
    }

    /** A photo, decrypted. The card photos need used_devices.view_seller, and every view is in the audit log. */
    public function photo(Request $request, string $device, int $photo, PhotoStore $store, Auditor $audit): Response
    {
        $user = $request->user();
        abort_unless((bool) $user?->can('used_devices.manage'), 403);
        $model = UsedDevice::query()->findOrFail($device);
        /** @var UsedDevicePhoto $file */
        $file = $model->photos()->whereKey($photo)->firstOrFail();

        if ($file->isIdCard()) {
            abort_unless((bool) $user?->can('used_devices.view_seller'), 403);
            $audit->record('used_devices.id_photo_viewed', "شاف صورة بطاقة بايع الجهاز المستعمل {$model->reference()}", $model, ['kind' => $file->kind]);
        }

        return $store->response($file);
    }
}
