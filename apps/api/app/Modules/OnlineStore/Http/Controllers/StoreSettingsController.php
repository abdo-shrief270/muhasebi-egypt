<?php

declare(strict_types=1);

namespace App\Modules\OnlineStore\Http\Controllers;

use App\Modules\Catalog\Contracts\StorefrontCatalog;
use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Identity\Contracts\ShopDirectory;
use App\Modules\OnlineStore\Models\OnlineStore;
use App\Modules\OnlineStore\Support\Slugs;
use App\Modules\OnlineStore\Support\StoreMedia;
use App\Support\Audit\Auditor;
use App\Support\Exceptions\DomainRuleException;
use App\Support\Modules\ModuleAccess;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;

/**
 * «المتجر الأونلاين» settings (online_store.manage): the address, the look, what it shows and how
 * customers order. The store starts closed, filled from the shop's profile.
 */
final class StoreSettingsController
{
    public function __construct(
        private readonly CurrentTenant $tenant,
        private readonly Auditor $audit,
        private readonly StoreMedia $media,
    ) {}

    public function show(ShopDirectory $shops, BranchDirectory $branches): JsonResponse
    {
        return response()->json(['data' => $this->present($this->store($shops, $branches))]);
    }

    public function update(Request $request, ShopDirectory $shops, BranchDirectory $branches, StorefrontCatalog $categories): JsonResponse
    {
        $store = $this->store($shops, $branches);
        $tenantId = $this->tenant->idOrFail();
        $data = $request->validate([
            'slug' => ['sometimes', 'string', 'max:40'],
            'mode' => ['sometimes', Rule::in(OnlineStore::MODES)],
            'name' => ['sometimes', 'string', 'max:120'],
            'tagline' => ['nullable', 'string', 'max:160'],
            'about' => ['nullable', 'string', 'max:3000'],
            'color' => ['sometimes', 'string', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'branch_id' => ['nullable', 'uuid', Rule::exists('branches', 'id')->where('tenant_id', $tenantId)],
            'whatsapp' => ['nullable', 'phone:EG'],
            'phone' => ['nullable', 'phone:EG'],
            'address' => ['nullable', 'string', 'max:255'],
            'map_url' => ['nullable', 'url:https', 'max:500'],
            'hours' => ['nullable', 'string', 'max:255'],
            'policy' => ['nullable', 'string', 'max:3000'],
            'facebook' => ['nullable', 'url:https', 'max:255'],
            'instagram' => ['nullable', 'url:https', 'max:255'],
            'show_out_of_stock' => ['sometimes', 'boolean'],
            'show_quantity' => ['sometimes', 'boolean'],
            'show_prices' => ['sometimes', 'boolean'],
            'show_models' => ['sometimes', 'boolean'],
            'show_latest' => ['sometimes', 'boolean'],
            'show_whatsapp' => ['sometimes', 'boolean'],
            'announcement' => ['nullable', 'string', 'max:160'],
            'show_brand' => ['sometimes', 'boolean'],
            'category_names' => ['nullable', 'array', 'max:200'],
            'category_names.*' => ['nullable', 'string', 'max:60'],
            'orders_from' => ['nullable', 'date_format:H:i', 'required_with:orders_until'],
            'orders_until' => ['nullable', 'date_format:H:i', 'required_with:orders_from', 'different:orders_from'],
            'pickup' => ['sometimes', 'boolean'],
            'delivery' => ['sometimes', 'boolean'],
            'min_order' => ['sometimes', 'integer', 'min:0', 'max:100000000'],
            'free_delivery_over' => ['nullable', 'integer', 'min:0', 'max:100000000'],
            'pay_cod' => ['sometimes', 'boolean'],
            'pay_transfer' => ['sometimes', 'boolean'],
            'transfer_instapay' => ['nullable', 'string', 'max:60'],
            'transfer_wallet' => ['nullable', 'phone:EG,mobile'],
            'repair_booking' => ['sometimes', 'boolean'],
            'repair_booking_note' => ['nullable', 'string', 'max:255'],
        ], [], [
            'slug' => 'عنوان المتجر', 'name' => 'اسم المتجر', 'whatsapp' => 'رقم الواتساب', 'phone' => 'التليفون',
            'map_url' => 'لينك الخريطة', 'color' => 'اللون',
            'min_order' => 'أقل طلب', 'free_delivery_over' => 'التوصيل ببلاش فوق', 'transfer_wallet' => 'رقم المحفظة', 'transfer_instapay' => 'عنوان InstaPay',
        ]);

        if (isset($data['slug'])) {
            $data['slug'] = strtolower(trim($data['slug']));
            if (! Slugs::valid($data['slug'])) {
                throw new DomainRuleException('العنوان لازم يبقى من 3 لـ 40 حرف إنجليزي صغير أو رقم أو شرطة (-)، ومايكونش كلمة محجوزة.', 'slug_invalid');
            }
            if (OnlineStore::withoutTenancy()->where('slug', $data['slug'])->where('id', '!=', $store->id)->exists()) {
                throw new DomainRuleException('العنوان ده محجوز لمحل تاني. جرّب عنوان تاني.', 'slug_taken');
            }
        }
        foreach (['whatsapp', 'phone', 'transfer_wallet'] as $field) {
            if (array_key_exists($field, $data) && $data[$field] !== null) {
                $data[$field] = (new PhoneNumber($data[$field], 'EG'))->formatE164();
            }
        }
        $mode = $data['mode'] ?? $store->mode;
        $whatsapp = array_key_exists('whatsapp', $data) ? $data['whatsapp'] : $store->whatsapp;
        if ($mode === 'whatsapp' && $whatsapp === null) {
            throw new DomainRuleException('اكتب رقم الواتساب اللي هتوصله الطلبات.', 'whatsapp_required');
        }
        $after = fn (string $field) => array_key_exists($field, $data) ? $data[$field] : $store->{$field};
        if ($mode === 'orders') {
            if (! $after('show_prices')) {
                throw new DomainRuleException('الطلبات في البرنامج محتاجة الأسعار تبان. اعرض الأسعار أو خلّي الطلبات على واتساب.', 'orders_need_prices');
            }
            if (! $after('pickup') && ! $after('delivery')) {
                throw new DomainRuleException('اختار الاستلام من المحل أو التوصيل (أو الاتنين).', 'fulfilment_required');
            }
            if (! $after('pay_cod') && ! $after('pay_transfer')) {
                throw new DomainRuleException('اختار طريقة دفع واحدة على الأقل.', 'payment_required');
            }
        }
        if ($after('pay_transfer') && $after('transfer_instapay') === null && $after('transfer_wallet') === null) {
            throw new DomainRuleException('اكتب عنوان InstaPay أو رقم المحفظة اللي الزبون هيحوّل عليه.', 'transfer_details_required');
        }

        if (($data['repair_booking'] ?? false) && ! app(ModuleAccess::class)->enabled('repairs')) {
            throw new DomainRuleException('حجز الصيانة محتاج موديول الصيانة يكون شغال عندك.', 'repairs_not_enabled');
        }

        if (array_key_exists('category_names', $data)) {
            // Only real categories of this shop, and only names that differ (blank = the app's name).
            $names = array_filter(array_map(fn ($n) => is_string($n) ? trim($n) : '', (array) $data['category_names']), fn (string $n) => $n !== '');
            $known = $categories->categoryNames();
            $data['category_names'] = array_filter($names, fn (string $n, $id) => isset($known[(int) $id]) && $known[(int) $id] !== $n, ARRAY_FILTER_USE_BOTH) ?: null;
        }

        $before = $store->mode;
        $store->fill($data)->save();
        $this->audit->record(
            'online_store.updated',
            $before !== $store->mode
                ? ($store->isOpen() ? "فتح المتجر الأونلاين ({$store->slug})" : 'قفل المتجر الأونلاين')
                : 'عدّل إعدادات المتجر الأونلاين',
            $store,
        );

        return response()->json(['data' => $this->present($store)]);
    }

    public function upload(Request $request, string $kind, ShopDirectory $shops, BranchDirectory $branches): JsonResponse
    {
        abort_unless(in_array($kind, ['logo', 'cover'], true), 404);
        $request->validate(['image' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:8192']], [], ['image' => 'الصورة']);
        $store = $this->store($shops, $branches);
        $this->media->put($store, $kind, $request->file('image'));

        return response()->json(['data' => $this->present($store->refresh())]);
    }

    public function removeMedia(string $kind, ShopDirectory $shops, BranchDirectory $branches): JsonResponse
    {
        abort_unless(in_array($kind, ['logo', 'cover'], true), 404);
        $store = $this->store($shops, $branches);
        $this->media->remove($store, $kind);

        return response()->json(['data' => $this->present($store->refresh())]);
    }

    /** The shop's store, made (closed) on first visit from its profile. */
    private function store(ShopDirectory $shops, BranchDirectory $branches): OnlineStore
    {
        $existing = OnlineStore::query()->first();
        if ($existing !== null) {
            return $existing;
        }
        $tenantId = $this->tenant->idOrFail();
        $shop = $shops->find($tenantId);
        $slug = strtolower((string) $shop?->code);
        if (! Slugs::valid($slug) || OnlineStore::withoutTenancy()->where('slug', $slug)->exists()) {
            $slug = 'shop-'.Str::lower(Str::random(6));
        }

        return OnlineStore::create([
            'tenant_id' => $tenantId,
            'slug' => $slug,
            'mode' => 'off',
            'name' => $shop?->name ?? 'المتجر',
            'branch_id' => $branches->mainBranchId(),
            'whatsapp' => $shop?->phone ?: null,
            'phone' => $shop?->phone ?: null,
        ])->refresh();
    }

    /** @return array<string, mixed> */
    private function present(OnlineStore $store): array
    {
        return [
            ...$store->toPublic(),
            'branch_id' => $store->branch_id,
            'show_out_of_stock' => $store->show_out_of_stock,
            'pickup' => $store->pickup,
            'delivery' => $store->delivery,
            'min_order' => $store->min_order,
            'free_delivery_over' => $store->free_delivery_over,
            'pay_cod' => $store->pay_cod,
            'pay_transfer' => $store->pay_transfer,
            'transfer_instapay' => $store->transfer_instapay,
            'transfer_wallet' => $store->transfer_wallet,
            'category_names' => (object) ($store->category_names ?? []),
            'orders_from' => OnlineStore::hhmm($store->orders_from),
            'orders_until' => OnlineStore::hhmm($store->orders_until),
            'repair_booking' => $store->repair_booking,
            'repair_booking_note' => $store->repair_booking_note,
            // «احجز صيانة» can only be offered by a shop that uses the repairs module.
            'repairs_available' => app(ModuleAccess::class)->enabled('repairs'),
            'url' => $store->url(),
            'feed_url' => $store->url().'/feed.xml',
        ];
    }
}
