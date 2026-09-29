<?php

declare(strict_types=1);

namespace App\Modules\Customers\Http\Controllers;

use App\Modules\Customers\Actions\EraseCustomerAction;
use App\Modules\Customers\Actions\ExportCustomerDataAction;
use App\Modules\Customers\Http\Resources\CustomerResource;
use App\Modules\Customers\Models\Customer;
use App\Modules\Customers\Models\CustomerPrivacySetting;
use App\Support\Audit\Auditor;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/** Personal Data Protection Law 151/2020: a customer's data export, erasure, and the shop's retention period. */
final class CustomerPrivacyController
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function export(Customer $customer, ExportCustomerDataAction $action): JsonResponse
    {
        return response()
            ->json($action->handle($customer), options: JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT)
            ->header('Content-Disposition', 'attachment; filename="customer-'.$customer->id.'.json"');
    }

    public function erase(Customer $customer, EraseCustomerAction $action): CustomerResource
    {
        return new CustomerResource($action->handle($customer));
    }

    public function settings(): JsonResponse
    {
        return $this->settingsResponse(CustomerPrivacySetting::query()->find($this->tenant->idOrFail()));
    }

    public function updateSettings(Request $request, Auditor $audit): JsonResponse
    {
        $data = $request->validate(
            ['retention_years' => ['present', 'nullable', 'integer', 'min:'.CustomerPrivacySetting::MIN_YEARS, 'max:'.CustomerPrivacySetting::MAX_YEARS]],
            attributes: ['retention_years' => 'مدة الاحتفاظ'],
        );
        $years = $data['retention_years'] === null ? null : (int) $data['retention_years'];

        $setting = CustomerPrivacySetting::query()->firstOrNew(['tenant_id' => $this->tenant->idOrFail()]);
        $before = $setting->retention_years;
        $setting->fill(['retention_years' => $years, 'updated_by_name' => $request->user()?->getAttribute('name')])->save();

        if ($before !== $years) {
            $audit->record(
                'customers.retention_changed',
                $years === null ? 'وقّف المسح التلقائي لبيانات العملاء' : "خلّى بيانات العملاء اللي مالهمش حركة من {$years} سنة تتمسح تلقائياً",
                $setting,
                ['retention_years' => [$before, $years]],
            );
        }

        return $this->settingsResponse($setting);
    }

    private function settingsResponse(?CustomerPrivacySetting $setting): JsonResponse
    {
        return response()->json(['data' => [
            'retention_years' => $setting?->retention_years,
            'updated_by_name' => $setting?->updated_by_name,
            'updated_at' => $setting?->updated_at?->toIso8601String(),
            'min_years' => CustomerPrivacySetting::MIN_YEARS,
            'max_years' => CustomerPrivacySetting::MAX_YEARS,
        ]]);
    }
}
