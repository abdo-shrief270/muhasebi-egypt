<?php

declare(strict_types=1);

namespace App\Modules\Customers\Contracts;

/** A customer as other modules see it. */
final readonly class CustomerSummary
{
    public function __construct(
        public string $id,
        public string $name,
        public ?string $phone,
        public int $balance,
        public ?int $creditLimit,
        public bool $isActive,
    ) {}

    /**
     * @return array{id: string, name: string, phone: string|null, balance: int, credit_limit: int|null, is_active: bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'phone' => $this->phone,
            'balance' => $this->balance,
            'credit_limit' => $this->creditLimit,
            'is_active' => $this->isActive,
        ];
    }
}
