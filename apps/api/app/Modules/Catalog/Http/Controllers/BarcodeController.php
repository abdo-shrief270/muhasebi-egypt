<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\GenerateBarcodesAction;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

final class BarcodeController
{
    public function generate(Request $request, GenerateBarcodesAction $action, CurrentTenant $tenant): JsonResponse
    {
        $validated = $request->validate([
            'variant_ids' => ['required', 'array', 'min:1', 'max:500'],
            'variant_ids.*' => ['uuid', 'distinct'],
        ]);

        return response()->json(['data' => $action->handle($tenant->idOrFail(), $validated['variant_ids'])]);
    }
}
