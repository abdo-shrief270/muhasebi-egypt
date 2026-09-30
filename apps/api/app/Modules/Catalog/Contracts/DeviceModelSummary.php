<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

/** A phone model from the shop's list (iPhone 13, Galaxy A54…), as other modules see it. */
final readonly class DeviceModelSummary
{
    public function __construct(
        public int $id,
        public ?int $brandId,
        public string $brandName,
        public string $name,
    ) {}

    /** "Apple iPhone 13" */
    public function fullName(): string
    {
        return trim("{$this->brandName} {$this->name}");
    }

    /**
     * @return array{id: int, brand_id: int|null, name: string, full_name: string}
     */
    public function toArray(): array
    {
        return ['id' => $this->id, 'brand_id' => $this->brandId, 'name' => $this->name, 'full_name' => $this->fullName()];
    }
}
