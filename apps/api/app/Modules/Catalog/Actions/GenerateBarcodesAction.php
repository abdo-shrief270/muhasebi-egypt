<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Actions;

use App\Modules\Catalog\Models\ProductVariant;
use App\Support\Audit\Auditor;
use App\Support\Numbering\DocumentNumbers;
use Illuminate\Support\Facades\DB;

/**
 * Gives variants without a barcode an in-store EAN-13 (prefix 2: reserved for in-store use, so it
 * never clashes with a manufacturer's code), numbered per shop, ready to print on labels.
 */
final class GenerateBarcodesAction
{
    public function __construct(
        private readonly DocumentNumbers $numbers,
        private readonly Auditor $audit,
    ) {}

    /**
     * @param  list<string>  $variantIds
     * @return array<string, string> variant id => barcode, for the ones that got a new one
     */
    public function handle(string $tenantId, array $variantIds): array
    {
        return DB::transaction(function () use ($tenantId, $variantIds): array {
            $assigned = [];

            $variants = ProductVariant::query()->whereIn('id', $variantIds)->whereNull('barcode')->lockForUpdate()->get();
            foreach ($variants as $variant) {
                do {
                    $code = self::ean13('2'.str_pad((string) $this->numbers->next($tenantId, 'barcode'), 11, '0', STR_PAD_LEFT));
                } while (ProductVariant::query()->where('barcode', $code)->exists());

                $variant->update(['barcode' => $code]);
                $assigned[$variant->id] = $code;
            }

            if ($assigned !== []) {
                $this->audit->record('products.barcodes_generated', 'عمل باركود لـ '.count($assigned).' صنف', properties: ['count' => count($assigned)], tenantId: $tenantId);
            }

            return $assigned;
        });
    }

    /** Appends the EAN-13 check digit to 12 digits. */
    public static function ean13(string $twelve): string
    {
        $sum = 0;
        foreach (str_split($twelve) as $i => $digit) {
            $sum += (int) $digit * ($i % 2 === 0 ? 1 : 3);
        }

        return $twelve.((10 - $sum % 10) % 10);
    }
}
