<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\DeviceModel;
use App\Modules\Catalog\Models\Product;
use App\Support\Exceptions\DomainRuleException;
use Illuminate\Database\Eloquent\Builder;

/**
 * Deletes a category, brand or phone model — only while no product uses it, so nothing
 * silently loses its category or its compatibility list.
 */
final class DeleteCatalogEntryAction
{
    public function handle(Category|Brand|DeviceModel $entry): void
    {
        $inUse = match (true) {
            $entry instanceof Category => $entry->products()->count(),
            $entry instanceof Brand => Product::query()
                ->where(fn (Builder $q) => $q
                    ->where('brand_id', $entry->id)
                    ->orWhereHas('deviceModels', fn (Builder $m) => $m->where('brand_id', $entry->id)))
                ->count(),
            $entry instanceof DeviceModel => Product::query()
                ->whereHas('deviceModels', fn (Builder $m) => $m->whereKey($entry->id))
                ->count(),
        };

        if ($inUse > 0) {
            throw new DomainRuleException(
                "مينفعش تمسح «{$entry->name}» لأن فيه {$inUse} صنف مربوط بيه. عدّل الأصناف دي الأول.",
                'catalog_entry_in_use',
                context: ['products' => $inUse],
            );
        }

        $entry->delete();
    }
}
