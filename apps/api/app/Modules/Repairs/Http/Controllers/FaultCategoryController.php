<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Controllers;

use App\Modules\Repairs\Http\Resources\FaultCategoryResource;
use App\Modules\Repairs\Models\FaultCategory;
use App\Modules\Repairs\Models\FaultType;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\Rule;

/**
 * The fault lists (part → faults) the intake screen picks from. Shops edit, hide and reorder
 * them, and give a fault a default labor price that's suggested on tickets.
 */
final class FaultCategoryController
{
    public function __construct(private readonly CurrentTenant $tenant) {}

    public function index(): AnonymousResourceCollection
    {
        return FaultCategoryResource::collection(
            FaultCategory::query()->with('types')->orderBy('sort')->get(),
        );
    }

    public function storeCategory(Request $request): JsonResponse
    {
        $data = $request->validate(['name' => ['required', 'string', 'max:60', $this->uniqueCategory()]], [], ['name' => 'اسم الجزء']);
        $category = FaultCategory::create(['tenant_id' => $this->tenant->idOrFail(), 'name' => $data['name'], 'sort' => (int) FaultCategory::query()->max('sort') + 1]);

        return (new FaultCategoryResource($category->load('types')))->response()->setStatusCode(201);
    }

    public function updateCategory(Request $request, int $category): FaultCategoryResource
    {
        $model = FaultCategory::query()->findOrFail($category);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:60', $this->uniqueCategory($model->id)],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ], [], ['name' => 'اسم الجزء']);
        $model->update($data);

        return new FaultCategoryResource($model->load('types'));
    }

    public function storeType(Request $request, int $category): JsonResponse
    {
        $model = FaultCategory::query()->findOrFail($category);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
            'default_labor_price' => ['nullable', 'integer', 'min:0', 'max:100000000000'],
        ], [], ['name' => 'اسم العطل']);
        $model->types()->create([
            'tenant_id' => $model->tenant_id,
            'name' => $data['name'],
            'default_labor_price' => $data['default_labor_price'] ?? null,
            'sort' => (int) $model->types()->max('sort') + 1,
        ]);

        return (new FaultCategoryResource($model->load('types')))->response()->setStatusCode(201);
    }

    /** Rename, re-price, hide / show or move a fault. Hidden faults stay on old tickets. */
    public function updateType(Request $request, int $type): FaultCategoryResource
    {
        $model = FaultType::query()->findOrFail($type);
        $data = $request->validate([
            'name' => ['sometimes', 'string', 'max:80'],
            'default_labor_price' => ['sometimes', 'nullable', 'integer', 'min:0', 'max:100000000000'],
            'is_active' => ['sometimes', 'boolean'],
            'sort' => ['sometimes', 'integer', 'min:0', 'max:1000'],
        ], [], ['name' => 'اسم العطل']);
        $model->update($data);

        return new FaultCategoryResource(FaultCategory::query()->with('types')->findOrFail($model->fault_category_id));
    }

    private function uniqueCategory(?int $ignore = null): object
    {
        return Rule::unique('fault_categories', 'name')->where(fn (Builder $q) => $q->where('tenant_id', $this->tenant->idOrFail()))->ignore($ignore);
    }
}
