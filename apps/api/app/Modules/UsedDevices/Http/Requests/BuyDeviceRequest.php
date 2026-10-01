<?php

declare(strict_types=1);

namespace App\Modules\UsedDevices\Http\Requests;

use App\Modules\UsedDevices\Enums\Grade;
use App\Modules\UsedDevices\Enums\PaymentMethod;
use App\Modules\UsedDevices\Support\Checklist;
use App\Support\Modules\FeatureAccess;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;
use Propaganistas\LaravelPhone\PhoneNumber;

/** multipart/form-data: the fields plus the card photos (id_front, id_back) and up to 4 device_photos[]. */
final class BuyDeviceRequest extends FormRequest
{
    private const MAX_MONEY = 100_000_000_000;

    /** Photos are downscaled by the web before upload; this is the ceiling (KB). */
    public const MAX_PHOTO_KB = 5120;

    public const MAX_DEVICE_PHOTOS = 4;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('used_devices.manage');
    }

    protected function prepareForValidation(): void
    {
        $phone = $this->input('seller_phone');
        if (is_string($phone) && trim($phone) !== '') {
            try {
                $this->merge(['seller_phone' => (new PhoneNumber($phone, 'EG'))->formatE164()]);
            } catch (\Throwable) {
                // The phone rule reports it.
            }
        }
        foreach (['device_model_id', 'battery_health', 'imei2', 'storage', 'color', 'notes', 'model_name', 'seller_phone'] as $field) {
            if ($this->input($field) === '' || $this->input($field) === 'null') {
                $this->merge([$field => null]);
            }
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $photo = ['file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:'.self::MAX_PHOTO_KB];

        return [
            'seller_name' => ['required', 'string', 'min:3', 'max:120'],
            'seller_phone' => ['nullable', 'string', 'phone:EG'],
            'seller_national_id' => ['required', 'string', 'max:20'],
            'device_model_id' => ['nullable', 'integer'],
            'model_name' => ['nullable', 'required_without:device_model_id', 'string', 'max:120'],
            'storage' => ['nullable', 'string', 'max:20'],
            'color' => ['nullable', 'string', 'max:40'],
            'imei' => ['required', 'string', 'max:20'],
            'imei2' => ['nullable', 'string', 'max:20'],
            'grade' => ['required', Rule::enum(Grade::class)],
            'checklist' => ['required', 'array'],
            'checklist.*' => [Rule::in(Checklist::VALUES)],
            'battery_health' => ['nullable', 'integer', 'min:1', 'max:100'],
            'notes' => ['nullable', 'string', 'max:500'],
            'purchase_price' => ['required', 'integer', 'min:1', 'max:'.self::MAX_MONEY],
            'asking_price' => ['required', 'integer', 'min:1', 'max:'.self::MAX_MONEY],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'id_front' => ['required', ...$photo],
            'id_back' => ['required', ...$photo],
            // The owner's «صور الجهاز إجباري» switch.
            'device_photos' => [app(FeatureAccess::class)->enabled('used_devices.device_photos_required') ? 'required' : 'nullable', 'array', 'max:'.self::MAX_DEVICE_PHOTOS],
            'device_photos.*' => $photo,
        ];
    }

    public function attributes(): array
    {
        return [
            'seller_name' => 'اسم البايع',
            'seller_phone' => 'موبايل البايع',
            'seller_national_id' => 'الرقم القومي',
            'model_name' => 'الموديل',
            'imei' => 'IMEI',
            'imei2' => 'IMEI التاني',
            'grade' => 'الفئة',
            'battery_health' => 'صحة البطارية',
            'purchase_price' => 'سعر الشراء',
            'asking_price' => 'سعر البيع',
            'payment_method' => 'طريقة الدفع',
            'id_front' => 'صورة البطاقة (وش)',
            'id_back' => 'صورة البطاقة (ضهر)',
            'device_photos' => 'صور الجهاز',
            'device_photos.*' => 'صورة الجهاز',
        ];
    }

    public function messages(): array
    {
        return [
            'seller_phone.phone' => 'رقم موبايل البايع مش صحيح.',
            'id_front.required' => 'صوّر وش البطاقة.',
            'id_back.required' => 'صوّر ضهر البطاقة.',
            'device_photos.max' => 'أقصى حاجة '.self::MAX_DEVICE_PHOTOS.' صور للجهاز.',
            'model_name.required_without' => 'اختار الموديل أو اكتبه.',
        ];
    }

    /**
     * @return array{name: string, phone: string|null, national_id: string}
     */
    public function seller(): array
    {
        return [
            'name' => (string) $this->validated('seller_name'),
            'phone' => $this->validated('seller_phone'),
            'national_id' => (string) $this->validated('seller_national_id'),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function device(): array
    {
        $v = $this->validated();

        return [
            'device_model_id' => isset($v['device_model_id']) ? (int) $v['device_model_id'] : null,
            'model_name' => $v['model_name'] ?? null,
            'storage' => $v['storage'] ?? null,
            'color' => $v['color'] ?? null,
            'imei' => (string) $v['imei'],
            'imei2' => $v['imei2'] ?? null,
            'grade' => Grade::from((string) $v['grade']),
            'checklist' => array_map('strval', (array) $v['checklist']),
            'battery_health' => isset($v['battery_health']) ? (int) $v['battery_health'] : null,
            'notes' => $v['notes'] ?? null,
            'purchase_price' => (int) $v['purchase_price'],
            'asking_price' => (int) $v['asking_price'],
            'payment_method' => PaymentMethod::from((string) $v['payment_method']),
        ];
    }

    /**
     * @return array{id_front: UploadedFile, id_back: UploadedFile, device: list<UploadedFile>}
     */
    public function photos(): array
    {
        /** @var UploadedFile $front */
        $front = $this->file('id_front');
        /** @var UploadedFile $back */
        $back = $this->file('id_back');

        return ['id_front' => $front, 'id_back' => $back, 'device' => array_values((array) $this->file('device_photos', []))];
    }
}
