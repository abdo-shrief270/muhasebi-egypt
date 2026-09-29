<?php

declare(strict_types=1);

namespace App\Modules\Repairs\Support;

use App\Modules\Repairs\Models\FaultType;
use App\Support\Exceptions\DomainRuleException;

/** Turns picked fault ids into the snapshot stored on a ticket (names as they were that day). */
final class Faults
{
    /**
     * @param  list<int>  $ids
     * @return list<array{id: int, category: string, name: string}>
     */
    public static function snapshot(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $types = FaultType::query()->with('category')->whereIn('id', $ids)->get()->keyBy('id');
        if ($types->count() !== count(array_unique($ids))) {
            throw new DomainRuleException('فيه عطل مش موجود في القايمة.', 'fault_not_found', 422);
        }

        return array_values(array_map(fn (int $id) => [
            'id' => $id,
            'category' => (string) $types[$id]->category?->name,
            'name' => $types[$id]->name,
        ], array_values(array_unique($ids))));
    }

    /**
     * Sum of the faults' default labor prices, as a suggestion.
     *
     * @param  list<array{id: int}>|null  $faults
     */
    public static function suggestedLabor(?array $faults): int
    {
        if (! $faults) {
            return 0;
        }

        return (int) FaultType::query()->whereIn('id', array_column($faults, 'id'))->sum('default_labor_price');
    }
}
