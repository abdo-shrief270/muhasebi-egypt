<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Http\Controllers;

use App\Modules\Repairs\Http\Resources\FaultCategoryResource;
use App\Modules\Repairs\Models\FaultCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

final class FaultCategoryController
{
    public function index(): AnonymousResourceCollection
    {
        return FaultCategoryResource::collection(
            FaultCategory::query()->with('types')->orderBy('sort')->get(),
        );
    }
}
