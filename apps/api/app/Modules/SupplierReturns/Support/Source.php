<?php

declare(strict_types=1);

namespace App\Modules\SupplierReturns\Support;

use App\Modules\SupplierReturns\Enums\SourceType;

/** Where a unit came from, and how that was found out. */
final readonly class Source
{
    public const SERIAL = 'serial';

    public const LOT = 'lot';

    public const MANUAL = 'manual';

    public function __construct(
        public SourceType $type,
        public string $id,
        public string $name,
        public ?string $phone = null,
        public ?string $doc = null,
        public ?string $detectedBy = null,
    ) {}

    public function detectedBy(string $how): self
    {
        return new self($this->type, $this->id, $this->name, $this->phone, $this->doc, $how);
    }

    public function key(): string
    {
        return $this->type->value.':'.$this->id;
    }

    /**
     * @return array<string, mixed> the bin item's source columns
     */
    public function columns(): array
    {
        return [
            'source_type' => $this->type,
            'source_id' => $this->id,
            'source_name' => $this->name,
            'source_doc' => $this->doc,
            'detected_by' => $this->detectedBy,
        ];
    }
}
