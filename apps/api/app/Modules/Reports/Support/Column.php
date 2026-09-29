<?php

declare(strict_types=1);

namespace App\Modules\Reports\Support;

/** A report column. Types: text, int, money (piasters), percent, date, datetime. */
final readonly class Column
{
    public function __construct(
        public string $key,
        public string $label,
        public string $type = 'text',
    ) {}

    /**
     * @return array{key: string, label: string, type: string}
     */
    public function toArray(): array
    {
        return ['key' => $this->key, 'label' => $this->label, 'type' => $this->type];
    }
}
