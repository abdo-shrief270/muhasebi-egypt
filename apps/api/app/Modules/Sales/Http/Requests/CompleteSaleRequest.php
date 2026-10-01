<?php

declare(strict_types=1);

namespace App\Modules\Sales\Http\Requests;

use App\Modules\Sales\Enums\PaymentMethod;
use App\Modules\Sales\Enums\PriceLevel;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

final class CompleteSaleRequest extends FormRequest
{
    private const MAX_MONEY = 100_000_000_000;

    /** A sale made offline is synced later with the time it was made: not from the future (beyond clock skew), not older than a week. */
    public const OFFLINE_MAX_AGE_DAYS = 7;

    public const OFFLINE_CLOCK_SKEW_MINUTES = 5;

    public function authorize(): bool
    {
        return (bool) $this->user()?->can('sales.sell');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'id' => ['nullable', 'uuid'],
            'price_level' => ['nullable', Rule::enum(PriceLevel::class)],
            'discount' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MONEY],
            'customer_id' => ['nullable', 'uuid'],
            'customer_name' => ['nullable', 'string', 'max:120'],
            'customer_phone' => ['nullable', 'string', 'max:20'],
            'notes' => ['nullable', 'string', 'max:500'],
            'items' => ['required', 'array', 'min:1', 'max:200'],
            'items.*.variant_id' => ['required', 'uuid', 'distinct'],
            'items.*.qty' => ['required', 'integer', 'min:1', 'max:100000'],
            // The price the offline POS showed (from its cached catalog); used only for offline sales.
            'items.*.unit_price' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MONEY],
            'items.*.discount' => ['nullable', 'integer', 'min:0', 'max:'.self::MAX_MONEY],
            'items.*.serials' => ['nullable', 'array', 'max:1000'],
            'items.*.serials.*' => ['string', 'regex:/^[A-Za-z0-9 \-\/]{4,48}$/'],
            'payments' => ['required', 'array', 'min:1', 'max:5'],
            'payments.*.method' => ['required', Rule::enum(PaymentMethod::class)],
            'payments.*.amount' => ['required', 'integer', 'min:1', 'max:'.self::MAX_MONEY],
            'payments.*.reference' => ['nullable', 'string', 'max:60'],
            // Queued on the POS while the internet was down: sold_at = when it was sold (device clock).
            'offline' => ['nullable', 'boolean'],
            'sold_at' => ['nullable', 'date'],
            // The cashier saw the owner's "below cost" warning and goes on.
            'confirm_below_cost' => ['nullable', 'boolean'],
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->has('sold_at') || $this->input('sold_at') === null) {
                return;
            }
            if (! $this->boolean('offline')) {
                $validator->errors()->add('sold_at', 'وقت البيع بيتبعت بس مع الفواتير اللي اتعملت أوفلاين.');

                return;
            }
            $soldAt = CarbonImmutable::parse((string) $this->input('sold_at'));
            if ($soldAt->isAfter(now()->addMinutes(self::OFFLINE_CLOCK_SKEW_MINUTES))) {
                $validator->errors()->add('sold_at', 'وقت البيع في المستقبل؛ اظبط ساعة الجهاز.');
            } elseif ($soldAt->isBefore(now()->subDays(self::OFFLINE_MAX_AGE_DAYS))) {
                $validator->errors()->add('sold_at', 'الفاتورة دي أقدم من '.self::OFFLINE_MAX_AGE_DAYS.' أيام ومينفعش تتسجل.');
            }
        }];
    }

    /** When an offline sale was made; null = now. */
    public function soldAt(): ?CarbonImmutable
    {
        $soldAt = $this->validated('sold_at');

        return $soldAt !== null && $this->boolean('offline') ? CarbonImmutable::parse((string) $soldAt) : null;
    }

    public function attributes(): array
    {
        return [
            'items' => 'الأصناف',
            'items.*.qty' => 'الكمية',
            'items.*.discount' => 'خصم الصنف',
            'items.*.serials.*' => 'السيريال',
            'payments' => 'الدفع',
            'payments.*.amount' => 'المبلغ',
            'payments.*.method' => 'طريقة الدفع',
            'sold_at' => 'وقت البيع',
        ];
    }

    /**
     * @return list<array{variant_id: string, qty: int, discount: int, serials: list<string>|null, unit_price: int|null}>
     */
    public function items(): array
    {
        return array_values(array_map(fn (array $i): array => [
            'variant_id' => (string) $i['variant_id'],
            'qty' => (int) $i['qty'],
            'discount' => (int) ($i['discount'] ?? 0),
            'serials' => isset($i['serials']) ? array_values(array_map('strval', $i['serials'])) : null,
            'unit_price' => isset($i['unit_price']) ? (int) $i['unit_price'] : null,
        ], $this->validated('items')));
    }

    /**
     * @return list<array{method: PaymentMethod, amount: int, reference: string|null}>
     */
    public function payments(): array
    {
        return array_values(array_map(fn (array $p): array => [
            'method' => PaymentMethod::from((string) $p['method']),
            'amount' => (int) $p['amount'],
            'reference' => $p['reference'] ?? null,
        ], $this->validated('payments')));
    }
}
