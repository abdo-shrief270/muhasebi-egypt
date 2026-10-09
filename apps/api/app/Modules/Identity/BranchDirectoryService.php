<?php

declare(strict_types=1);

namespace App\Modules\Identity;

use App\Modules\Identity\Contracts\BranchDirectory;
use App\Modules\Identity\Enums\Governorate;
use App\Modules\Identity\Models\Branch;
use App\Modules\Identity\Models\User;
use Illuminate\Contracts\Auth\Authenticatable;

final class BranchDirectoryService implements BranchDirectory
{
    public function __construct(private readonly BranchAccess $access) {}

    public function accessibleBranches(Authenticatable $user): array
    {
        if (! $user instanceof User) {
            return [];
        }
        $ids = $this->access->branchIdsFor($user);
        $names = Branch::withoutTenancy()->whereIn('id', $ids)->pluck('name', 'id');

        $branches = [];
        foreach ($ids as $id) {
            $branches[$id] = (string) $names[$id];
        }

        return $branches;
    }

    public function all(): array
    {
        return Branch::query()->where('is_active', true)->orderByDesc('is_main')->orderBy('name')
            ->pluck('name', 'id')->map(fn ($n) => (string) $n)->all();
    }

    public function mainBranchId(): ?string
    {
        return Branch::query()->where('is_main', true)->value('id');
    }

    public function locations(): array
    {
        return Branch::query()->where('is_active', true)->orderByDesc('is_main')->orderBy('name')->get()
            ->mapWithKeys(fn (Branch $b) => [$b->id => [
                'name' => $b->name,
                'address' => $b->address,
                'phone' => $b->phone,
                'governorate' => $b->governorate,
                'governorate_label' => $b->governorate === null ? null : Governorate::tryFrom($b->governorate)?->label(),
                'area' => $b->area,
                'latitude' => $b->latitude === null ? null : (float) $b->latitude,
                'longitude' => $b->longitude === null ? null : (float) $b->longitude,
                'is_main' => $b->is_main,
            ]])->all();
    }
}
