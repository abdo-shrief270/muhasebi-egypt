<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Contracts;

/**
 * What other modules may know about a sellable variant. Prices are piasters.
 */
final readonly class VariantSummary
{
    public function __construct(
        public string $id,
        public string $productId,
        public string $productName,
        public ?string $variantName,
        public ?string $barcode,
        public ?string $sku,
        public int $categoryId,
        public string $categoryName,
        public int $priceRetail,
        public int $minStock,
        public bool $isActive,
        public bool $trackSerial,
    ) {}

    /** "جراب سيليكون — أسود" */
    public function displayName(): string
    {
        return $this->variantName ? "{$this->productName} — {$this->variantName}" : $this->productName;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'product_id' => $this->productId,
            'product_name' => $this->productName,
            'variant_name' => $this->variantName,
            'display_name' => $this->displayName(),
            'barcode' => $this->barcode,
            'sku' => $this->sku,
            'category' => ['id' => $this->categoryId, 'name' => $this->categoryName],
            'price_retail' => $this->priceRetail,
            'min_stock' => $this->minStock,
            'is_active' => $this->isActive,
            'track_serial' => $this->trackSerial,
        ];
    }
}
