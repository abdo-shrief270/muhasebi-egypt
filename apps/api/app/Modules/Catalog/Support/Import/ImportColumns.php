<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support\Import;

use App\Modules\Catalog\Support\SearchText;

/**
 * The product sheet's columns: the template's header, the other headers shops tend to use,
 * and which ones are required. One row per variant; rows with the same name and category
 * become one product.
 */
final class ImportColumns
{
    /**
     * key => [template header, required, other accepted headers]
     *
     * @return array<string, array{0: string, 1: bool, 2: list<string>}>
     */
    public static function all(): array
    {
        return [
            'name' => ['اسم الصنف', true, ['الصنف', 'الاسم', 'اسم المنتج', 'name', 'product']],
            'category' => ['التصنيف', true, ['القسم', 'category']],
            'brand' => ['الماركة', false, ['البراند', 'brand']],
            'sku' => ['كود الصنف', false, ['الكود', 'sku', 'code']],
            'variant' => ['النوع', false, ['المتغير', 'اللون', 'variant']],
            'quality' => ['الجودة', false, ['quality']],
            'barcode' => ['الباركود', false, ['باركود', 'barcode']],
            'price_retail' => ['سعر القطاعي', true, ['السعر', 'سعر البيع', 'القطاعي', 'price', 'retail']],
            'price_wholesale' => ['سعر الجملة', false, ['الجملة', 'wholesale']],
            'price_technician' => ['سعر الفني', false, ['الفني', 'technician']],
            'price_online' => ['سعر الأونلاين', false, ['الأونلاين', 'online']],
            'min_stock' => ['حد النواقص', false, ['الحد الأدنى', 'min stock']],
            'models' => ['الموديلات', false, ['بيركب على', 'التوافق', 'models']],
        ];
    }

    /**
     * Maps a sheet's header row to column keys; unknown headers are ignored.
     *
     * @param  list<mixed>  $headerRow
     * @return array<int, string> cell index => column key
     */
    public static function mapHeader(array $headerRow): array
    {
        $lookup = [];
        foreach (self::all() as $key => [$header, , $aliases]) {
            foreach ([$header, ...$aliases] as $name) {
                $lookup[self::normalizeHeader($name)] = $key;
            }
        }

        $map = [];
        foreach ($headerRow as $index => $cell) {
            $key = $lookup[self::normalizeHeader((string) $cell)] ?? null;
            if ($key !== null && ! in_array($key, $map, true)) {
                $map[$index] = $key;
            }
        }

        return $map;
    }

    /**
     * @param  array<int, string>  $map
     * @return list<string> template headers of required columns the sheet lacks
     */
    public static function missingRequired(array $map): array
    {
        $missing = [];
        foreach (self::all() as $key => [$header, $required]) {
            if ($required && ! in_array($key, $map, true)) {
                $missing[] = $header;
            }
        }

        return $missing;
    }

    private static function normalizeHeader(string $header): string
    {
        return str_replace([' ', '*', '(', ')', ':'], '', SearchText::normalize($header));
    }
}
