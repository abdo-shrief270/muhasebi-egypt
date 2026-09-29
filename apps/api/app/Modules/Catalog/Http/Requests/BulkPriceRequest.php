<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Actions\BulkPriceAction;
use App\Modules\Catalog\Models\ProductVariant;
use App\Modules\Catalog\Support\PriceRule;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A bulk price edit: which variants (filters), and the rule. Starting from the cost needs products.view_cost.
 */
final class BulkPriceRequest extends FormRequest
{
    public function authorize(): bool
    {
        $user = $this->user();

        return $user !== null && $user->can('products.manage')
            && ($this->input('base') !== 'cost' || $user->can('products.view_cost'));
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->idOrFail();
        $ofTenant = fn (Builder $q) => $q->where('tenant_id', $tenantId);

        return [
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')->where($ofTenant)],
            'brand_id' => ['nullable', 'integer', Rule::exists('brands', 'id')->where($ofTenant)],
            'device_model_id' => ['nullable', 'integer', Rule::exists('device_models', 'id')->where($ofTenant)],
            'q' => ['nullable', 'string', 'max:120'],
            'include_inactive' => ['sometimes', 'boolean'],
            'variant_ids' => ['nullable', 'array', 'max:'.BulkPriceAction::MAX_VARIANTS],
            'variant_ids.*' => ['uuid'],
            'exclude' => ['sometimes', 'array'],
            'exclude.*' => ['uuid'],

            'field' => ['required', Rule::in(ProductVariant::PRICE_FIELDS)],
            'base' => ['required_unless:change,set', 'nullable', Rule::in([...ProductVariant::PRICE_FIELDS, 'cost'])],
            'change' => ['required', Rule::in(PriceRule::CHANGES)],
            // percent: -90 … +1000; amount: piasters, may be negative; set: piasters > 0
            'value' => ['required', 'numeric', ...match ($this->input('change')) {
                'percent' => ['min:-90', 'max:1000'],
                'set' => ['integer', 'min:1', 'max:1000000000'],
                default => ['integer', 'min:-1000000000', 'max:1000000000'],
            }],
            'round_to' => ['sometimes', 'integer', Rule::in([0, 50, 100, 500, 1000, 5000, 10000])],
            'rounding' => ['sometimes', Rule::in(PriceRule::ROUNDING)],
        ];
    }

    /** @return array<string, mixed> */
    public function filters(): array
    {
        return [
            'category_id' => $this->filled('category_id') ? $this->integer('category_id') : null,
            'brand_id' => $this->filled('brand_id') ? $this->integer('brand_id') : null,
            'device_model_id' => $this->filled('device_model_id') ? $this->integer('device_model_id') : null,
            'q' => $this->input('q'),
            'variant_ids' => $this->input('variant_ids') ?: null,
            'include_inactive' => $this->boolean('include_inactive'),
        ];
    }

    public function priceRule(): PriceRule
    {
        $change = (string) $this->input('change');

        return new PriceRule(
            field: (string) $this->input('field'),
            base: (string) ($this->input('base') ?? $this->input('field')),
            change: $change,
            value: $change === 'percent' ? (int) round((float) $this->input('value') * 100) : (int) $this->input('value'),
            step: $this->integer('round_to'),
            rounding: (string) $this->input('rounding', 'nearest'),
        );
    }

    /** @return list<string> */
    public function excluded(): array
    {
        return array_values($this->input('exclude', []));
    }
}
