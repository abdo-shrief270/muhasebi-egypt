<?php

declare(strict_types=1);

namespace App\Modules\Identity\Http\Controllers;

use App\Modules\Identity\Http\Resources\TenantResource;
use App\Modules\Identity\Models\Tenant;
use App\Modules\Identity\Support\ReceiptSettings;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Propaganistas\LaravelPhone\PhoneNumber;

/** The shop's name and phone, and what its receipts print (owner only, /settings/shop). */
final class ShopProfileController
{
    public function show(CurrentTenant $current): TenantResource
    {
        return new TenantResource(Tenant::query()->findOrFail($current->idOrFail()));
    }

    public function update(Request $request, CurrentTenant $current, Auditor $audit): TenantResource
    {
        $phone = $request->input('phone');
        if (is_string($phone) && $phone !== '') {
            try {
                $request->merge(['phone' => (new PhoneNumber($phone, 'EG'))->formatE164()]);
            } catch (\Throwable) {
                // Left as typed; the phone rule reports it.
            }
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'phone' => ['required', 'phone:EG'],
            'tax_number' => ['nullable', 'string', 'max:30'],
            'commercial_register' => ['nullable', 'string', 'max:30'],
            'footer' => ['nullable', 'string', 'max:200'],
        ], [], [
            'name' => 'اسم المحل',
            'phone' => 'موبايل المحل',
            'tax_number' => 'رقم التسجيل الضريبي',
            'commercial_register' => 'رقم السجل التجاري',
            'footer' => 'آخر سطر في الإيصال',
        ]);

        return DB::transaction(function () use ($data, $current, $audit): TenantResource {
            $tenant = Tenant::query()->lockForUpdate()->findOrFail($current->idOrFail());
            $before = $tenant->name;
            $tenant->fill(['name' => $data['name'], 'phone' => $data['phone']]);
            ReceiptSettings::apply($tenant, $data);
            $tenant->save();
            $audit->record('shop.profile_updated', $before === $tenant->name
                ? 'عدّل بيانات المحل والإيصال'
                : "غيّر اسم المحل من «{$before}» لـ «{$tenant->name}»", $tenant, tenantId: $tenant->id);

            return new TenantResource($tenant);
        });
    }
}
