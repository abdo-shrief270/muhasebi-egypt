<?php

declare(strict_types=1);

namespace App\Support\Tenancy;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

final class SharedTenantScope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $tenantId = app(CurrentTenant::class)->id();

        if ($tenantId === null) {
            $builder->whereRaw('1 = 0');

            return;
        }

        /** @var list<string> $columns */
        $columns = $model::tenantColumns();

        $builder->where(function (Builder $query) use ($columns, $model, $tenantId): void {
            foreach ($columns as $column) {
                $query->orWhere($model->qualifyColumn($column), $tenantId);
            }
        });
    }
}
