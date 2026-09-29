<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Http\Controllers;

use App\Modules\Catalog\Actions\ImportProductsAction;
use App\Modules\Catalog\Http\Requests\ImportProductsRequest;
use App\Modules\Catalog\Support\Import\ImportTemplate;
use App\Support\Tenancy\CurrentTenant;
use Illuminate\Http\JsonResponse;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

final class ProductImportController
{
    public function template(ImportTemplate $template): BinaryFileResponse
    {
        $path = tempnam(sys_get_temp_dir(), 'products-template-').'.xlsx';
        $template->write($path);

        return response()->download($path, 'نموذج-الأصناف.xlsx')->deleteFileAfterSend();
    }

    /** Reads the file and reports what an import would do; writes nothing. */
    public function preview(ImportProductsRequest $request, ImportProductsAction $action): JsonResponse
    {
        return response()->json(['data' => $action->preview($request->sheet())->summary()]);
    }

    public function store(ImportProductsRequest $request, ImportProductsAction $action, CurrentTenant $tenant): JsonResponse
    {
        $plan = $action->handle($tenant->idOrFail(), $request->sheet(), $request->boolean('skip_invalid'));

        return response()->json(['data' => $plan->summary()]);
    }
}
