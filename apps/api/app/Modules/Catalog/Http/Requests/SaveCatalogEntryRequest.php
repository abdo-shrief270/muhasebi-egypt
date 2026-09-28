<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Requests;

use App\Modules\Catalog\Enums\CategoryType;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\DeviceModel;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Categories, brands and phone models: a name that is unique in its list, plus a type for categories.
 */
final class SaveCatalogEntryRequest extends FormRequest
{
    public function authorize(): bool
    {
        return (bool) $this->user()?->can('products.manage');
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $tenantId = app(CurrentTenant::class)->idOrFail();
        $ofTenant = fn (Builder $q) => $q->where('tenant_id', $tenantId);

        return match (true) {
            $this->routeIs('catalog.categories.*') => [
                'name' => ['required', 'string', 'max:120', Rule::unique('categories', 'name')->where($ofTenant)->ignore($this->entry(Category::class)?->id)],
                'type' => [$this->entry(Category::class) ? 'sometimes' : 'required', Rule::enum(CategoryType::class)],
            ],
            $this->routeIs('catalog.brands.*') => [
                'name' => ['required', 'string', 'max:120', Rule::unique('brands', 'name')->where($ofTenant)->ignore($this->entry(Brand::class)?->id)],
            ],
            default => [
                'name' => ['required', 'string', 'max:120', Rule::unique('device_models', 'name')
                    ->where($ofTenant)
                    ->where('brand_id', $this->entry(DeviceModel::class)->brand_id ?? $this->entry(Brand::class)?->id)
                    ->ignore($this->entry(DeviceModel::class)?->id)],
            ],
        };
    }

    public function attributes(): array
    {
        return ['name' => 'الاسم', 'type' => 'النوع'];
    }

    public function messages(): array
    {
        return ['name.unique' => 'الاسم ده موجود بالفعل.'];
    }

    /**
     * @template T of Category|Brand|DeviceModel
     *
     * @param  class-string<T>  $class
     * @return T|null
     */
    private function entry(string $class): ?object
    {
        foreach ($this->route()?->parameters() ?? [] as $value) {
            if ($value instanceof $class) {
                return $value;
            }
        }

        return null;
    }
}
