<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support\Import;

use App\Modules\Catalog\Enums\QualityGrade;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use App\Modules\Catalog\Models\DeviceModel;
use App\Modules\Catalog\Models\Product;
use App\Modules\Catalog\Models\ProductVariant;
use App\Support\Text\SearchText;

/**
 * Turns sheet rows into a plan without touching the database: which rows are invalid and why,
 * which products and variants get created, and which existing variants get new prices.
 *
 * Matching, in order: a row whose barcode already exists updates that variant; otherwise it joins
 * the product with the same name and category (existing or earlier in the file), updating the
 * variant with the same name there or adding a new one. Unknown categories and brands are created;
 * unknown phone models are skipped with a warning.
 */
final class ImportPlanner
{
    private const MAX_PRICE_POUNDS = 1_000_000_000;

    /** @var array<string, int> */
    private array $categories = [];

    /** @var array<string, int> */
    private array $brands = [];

    /** @var array<string, list<int>> normalised "brand model" and bare model name => ids */
    private array $models = [];

    /** @var array<string, array{variant_id: string, product_id: string}> */
    private array $barcodes = [];

    /** @var array<string, string> lower(sku) => product id */
    private array $skus = [];

    /** @var array<string, string> "search_name|category_id" => product id */
    private array $products = [];

    /** @var array<string, string> "product id|normalised variant name" => variant id */
    private array $variantsByName = [];

    /** @var array<string, true> existing variants already given opening stock in this file */
    private array $stockPlanned = [];

    /**
     * @param  list<string>  $variantsWithStock  variants that already moved in the import's branch
     * @param  bool  $canSetStock  may record opening stock (inventory.adjust)
     * @param  bool  $canSetCost  may enter costs (products.view_cost)
     */
    public function __construct(
        private readonly array $variantsWithStock = [],
        private readonly bool $canSetStock = false,
        private readonly bool $canSetCost = false,
    ) {
        foreach (Category::query()->get(['id', 'name']) as $c) {
            $this->categories[SearchText::normalize($c->name)] = $c->id;
        }
        foreach (Brand::query()->get(['id', 'name']) as $b) {
            $this->brands[SearchText::normalize($b->name)] = $b->id;
        }
        foreach (DeviceModel::query()->get(['id', 'name', 'search_name']) as $m) {
            $this->models[$m->search_name][] = $m->id;
            $this->models[SearchText::normalize($m->name)][] = $m->id;
        }
        foreach (Product::query()->get(['id', 'search_name', 'category_id', 'sku']) as $p) {
            $this->products["{$p->search_name}|{$p->category_id}"] = $p->id;
            if ($p->sku !== null) {
                $this->skus[mb_strtolower($p->sku)] = $p->id;
            }
        }
        foreach (ProductVariant::query()->get(['id', 'product_id', 'name', 'barcode']) as $v) {
            if ($v->barcode !== null) {
                $this->barcodes[$v->barcode] = ['variant_id' => $v->id, 'product_id' => $v->product_id];
            }
            $this->variantsByName[$v->product_id.'|'.SearchText::normalize((string) $v->name)] = $v->id;
        }
    }

    /**
     * @param  list<array{row: int, cells: array<string, string>}>  $rows
     */
    public function plan(array $rows): ImportPlan
    {
        $plan = new ImportPlan;
        $seenBarcodes = [];
        $seenSkus = [];

        foreach ($rows as ['row' => $line, 'cells' => $cells]) {
            $errors = [];
            $warnings = [];

            $name = mb_substr(trim($cells['name'] ?? ''), 0, 191);
            $categoryName = trim($cells['category'] ?? '');
            $brandName = trim($cells['brand'] ?? '');
            $sku = trim($cells['sku'] ?? '') ?: null;
            $variantName = trim($cells['variant'] ?? '') ?: null;
            $barcode = $this->cleanCode($cells['barcode'] ?? '') ?: null;

            if ($name === '') {
                $errors[] = 'اسم الصنف فاضي';
            } elseif (mb_strlen(trim($cells['name'])) > 190) {
                $errors[] = 'اسم الصنف أطول من 190 حرف';
            }
            if ($categoryName === '') {
                $errors[] = 'التصنيف فاضي';
            }
            if ($sku !== null && mb_strlen($sku) > 64) {
                $errors[] = 'كود الصنف أطول من 64 حرف';
            }
            if ($barcode !== null && mb_strlen($barcode) > 64) {
                $errors[] = 'الباركود أطول من 64 حرف';
            }
            if ($variantName !== null && mb_strlen($variantName) > 120) {
                $errors[] = 'اسم النوع أطول من 120 حرف';
            }

            $prices = [];
            foreach (['price_retail' => 'سعر القطاعي', 'price_wholesale' => 'سعر الجملة', 'price_technician' => 'سعر الفني', 'price_online' => 'سعر الأونلاين'] as $field => $label) {
                $raw = trim($cells[$field] ?? '');
                if ($raw === '') {
                    if ($field === 'price_retail') {
                        $errors[] = 'سعر القطاعي فاضي';
                    }
                    $prices[$field] = null;

                    continue;
                }
                $pounds = self::number($raw);
                if ($pounds === null || $pounds < 0 || $pounds > self::MAX_PRICE_POUNDS) {
                    $errors[] = "{$label} مش رقم صحيح ({$raw})";

                    continue;
                }
                $prices[$field] = (int) round($pounds * 100);
            }

            $minStock = null;
            if (($raw = trim($cells['min_stock'] ?? '')) !== '') {
                $value = self::number($raw);
                if ($value === null || $value < 0 || $value > 1_000_000 || floor($value) != $value) {
                    $errors[] = "حد النواقص لازم يبقى رقم صحيح ({$raw})";
                } else {
                    $minStock = (int) $value;
                }
            }

            $openingQty = 0;
            if (($raw = trim($cells['opening_qty'] ?? '')) !== '') {
                $value = self::number($raw);
                if ($value === null || $value < 0 || $value > 1_000_000 || floor($value) != $value) {
                    $errors[] = "الكمية لازم تبقى رقم صحيح ({$raw})";
                } elseif (! $this->canSetStock) {
                    $warnings[] = 'الكمية اتجاهلت: مش معاك صلاحية «الجرد وتسوية المخزون»';
                } else {
                    $openingQty = (int) $value;
                }
            }

            $unitCost = 0;
            if (($raw = trim($cells['unit_cost'] ?? '')) !== '' && $this->canSetCost) {
                $pounds = self::number($raw);
                if ($pounds === null || $pounds < 0 || $pounds > self::MAX_PRICE_POUNDS) {
                    $errors[] = "سعر التكلفة مش رقم صحيح ({$raw})";
                } else {
                    $unitCost = (int) round($pounds * 100);
                }
            }
            $stock = $openingQty > 0 ? ['qty' => $openingQty, 'unit_cost' => $unitCost] : null;

            $quality = null;
            if (($raw = trim($cells['quality'] ?? '')) !== '') {
                $quality = self::quality($raw);
                if ($quality === null) {
                    $warnings[] = "الجودة «{$raw}» مش معروفة (أصلي، سيرفس باك، هاي كوبي، كوبي) — اتسابت فاضية";
                }
            }

            if ($barcode !== null) {
                if (isset($seenBarcodes[$barcode])) {
                    $errors[] = "الباركود {$barcode} متكرر (أول مرة في صف {$seenBarcodes[$barcode]})";
                } else {
                    $seenBarcodes[$barcode] = $line;
                }
            }

            $modelIds = [];
            foreach ($this->splitList($cells['models'] ?? '') as $modelName) {
                $ids = $this->models[SearchText::normalize($modelName)] ?? [];
                $ids = array_values(array_unique($ids));
                if (count($ids) === 1) {
                    $modelIds[] = $ids[0];
                } elseif ($ids === []) {
                    $warnings[] = "الموديل «{$modelName}» مش موجود — ضيفه من «التصنيفات والماركات» أو اكتبه بالماركة (مثلاً Apple iPhone 13)";
                } else {
                    $warnings[] = "الموديل «{$modelName}» موجود في أكتر من ماركة — اكتبه بالماركة (مثلاً Samsung Galaxy A54)";
                }
            }

            if ($errors !== []) {
                $plan->rowFailed($line, $errors, $warnings);

                continue;
            }

            $categoryKey = SearchText::normalize($categoryName);
            if (! isset($this->categories[$categoryKey])) {
                $plan->newCategory($categoryKey, $categoryName);
            }
            $brandKey = $brandName === '' ? null : SearchText::normalize($brandName);
            if ($brandKey !== null && ! isset($this->brands[$brandKey])) {
                $plan->newBrand($brandKey, $brandName);
            }

            $variant = [
                'name' => $variantName,
                'quality_grade' => $quality?->value,
                'barcode' => $barcode,
                ...$prices,
                'min_stock' => $minStock,
            ];

            // 1) Existing barcode: that variant gets the new prices.
            if ($barcode !== null && isset($this->barcodes[$barcode])) {
                $variantId = $this->barcodes[$barcode]['variant_id'];
                $plan->updateVariant($line, $variantId, $this->barcodes[$barcode]['product_id'], $variant, $modelIds, $this->openingFor($variantId, $stock, $warnings));
                $plan->rowOk($line, $warnings);

                continue;
            }

            // 2) Same name + category: existing product, or one planned earlier in this file.
            $searchName = SearchText::normalize($name);
            $categoryId = $this->categories[$categoryKey] ?? null;
            $existingProductId = $categoryId !== null ? ($this->products["{$searchName}|{$categoryId}"] ?? null) : null;
            $groupKey = "{$searchName}|{$categoryKey}";

            if ($existingProductId !== null) {
                $existingVariantId = $this->variantsByName[$existingProductId.'|'.SearchText::normalize((string) $variantName)] ?? null;
                if ($existingVariantId !== null) {
                    $plan->updateVariant($line, $existingVariantId, $existingProductId, $variant, $modelIds, $this->openingFor($existingVariantId, $stock, $warnings));
                } else {
                    $plan->addVariant($line, $existingProductId, $variant, $modelIds, $stock);
                }
                $plan->rowOk($line, $warnings);

                continue;
            }

            // 3) New product (the first row of a group decides brand and SKU).
            if (! $plan->hasNewProduct($groupKey)) {
                $skuKey = $sku === null ? null : mb_strtolower($sku);
                if ($skuKey !== null && (isset($this->skus[$skuKey]) || isset($seenSkus[$skuKey]))) {
                    $plan->rowFailed($line, ["كود الصنف {$sku} مستخدم لصنف تاني"], $warnings);

                    continue;
                }
                if ($skuKey !== null) {
                    $seenSkus[$skuKey] = true;
                }
                $plan->newProduct($groupKey, [
                    'name' => $name,
                    'category_key' => $categoryKey,
                    'brand_key' => $brandKey,
                    'sku' => $sku,
                ]);
            }

            $plan->newProductVariant($line, $groupKey, $variant, $modelIds, $stock);
            $plan->rowOk($line, $warnings);
        }

        return $plan;
    }

    /**
     * Opening stock only for a variant that never moved here, and only once per import.
     *
     * @param  array{qty: int, unit_cost: int}|null  $stock
     * @param  list<string>  $warnings
     * @return array{qty: int, unit_cost: int}|null
     */
    private function openingFor(string $variantId, ?array $stock, array &$warnings): ?array
    {
        if ($stock === null) {
            return null;
        }
        if (in_array($variantId, $this->variantsWithStock, true) || isset($this->stockPlanned[$variantId])) {
            $warnings[] = 'الكمية اتجاهلت لأن النوع ده ليه رصيد في الفرع بالفعل — عدّلها من «المخزون» بالجرد';

            return null;
        }
        $this->stockPlanned[$variantId] = true;

        return $stock;
    }

    /** Numbers as shops type them: Arabic digits, thousands separators, "150 ج". */
    public static function number(string $raw): ?float
    {
        $s = strtr($raw, [
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4', '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4', '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٫' => '.', '٬' => '', ',' => '', 'ج' => '', 'جنيه' => '', 'EGP' => '', 'egp' => '', ' ' => '',
        ]);

        return is_numeric($s) ? (float) $s : null;
    }

    public static function quality(string $raw): ?QualityGrade
    {
        $key = str_replace([' ', '-', '_'], '', SearchText::normalize($raw));

        foreach (QualityGrade::cases() as $grade) {
            $names = [$grade->value, $grade->label()];
            if ($grade === QualityGrade::ServicePack) {
                $names[] = 'سيرفس';
            }
            if ($grade === QualityGrade::Original) {
                $names[] = 'اورجينال';
            }
            foreach ($names as $name) {
                if (str_replace([' ', '-', '_'], '', SearchText::normalize($name)) === $key) {
                    return $grade;
                }
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function splitList(string $raw): array
    {
        return array_values(array_filter(array_map(trim(...), preg_split('/[,،\/|\n;]+/u', $raw) ?: [])));
    }

    /** Barcodes typed as numbers lose nothing; stray spaces go. */
    private function cleanCode(string $raw): string
    {
        return preg_replace('/\s+/u', '', $raw) ?? $raw;
    }
}
