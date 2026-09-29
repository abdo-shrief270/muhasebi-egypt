<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Support\Import;

use App\Modules\Catalog\Enums\QualityGrade;
use App\Modules\Catalog\Models\Brand;
use App\Modules\Catalog\Models\Category;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Entity\SheetView;
use OpenSpout\Writer\XLSX\Writer;

/**
 * The .xlsx shops fill in: the product sheet with example rows, plus a help sheet listing
 * the shop's own categories, brands and the accepted quality names.
 */
final class ImportTemplate
{
    public function write(string $path): void
    {
        $writer = new Writer;
        $writer->openToFile($path);

        $header = (new Style)->setFontBold()->setBackgroundColor('E3F4F1');
        $rtl = (new SheetView)->setRightToLeft(true);

        $sheet = $writer->getCurrentSheet();
        $sheet->setName('الأصناف');
        $sheet->setSheetView($rtl);
        $sheet->setColumnWidth(28, 1);
        $sheet->setColumnWidthForRange(16, 2, count(ImportColumns::all()));

        $writer->addRow(Row::fromValues(array_map(
            fn (array $column): string => $column[0].($column[1] ? ' *' : ''),
            array_values(ImportColumns::all()),
        ), $header));

        foreach ($this->examples() as $example) {
            $writer->addRow(Row::fromValues($example));
        }

        $help = $writer->addNewSheetAndMakeItCurrent();
        $help->setName('مساعدة');
        $help->setSheetView((new SheetView)->setRightToLeft(true));
        $help->setColumnWidthForRange(30, 1, 3);

        $writer->addRow(Row::fromValues(['التصنيفات الموجودة', 'الماركات الموجودة', 'الجودة'], $header));
        $categories = Category::query()->orderBy('sort')->pluck('name')->all();
        $brands = Brand::query()->orderBy('sort')->pluck('name')->all();
        $grades = array_map(fn (QualityGrade $g) => $g->label(), QualityGrade::cases());
        for ($i = 0, $n = max(count($categories), count($brands), count($grades)); $i < $n; $i++) {
            $writer->addRow(Row::fromValues([$categories[$i] ?? '', $brands[$i] ?? '', $grades[$i] ?? '']));
        }

        $writer->addRow(Row::fromValues(['']));
        foreach ([
            'كل صف = نوع واحد (لون / سعة / جودة). الصفوف اللي ليها نفس الاسم والتصنيف بتبقى صنف واحد بأكتر من نوع.',
            'الأعمدة اللي عليها * لازم تتملى. الأسعار بالجنيه.',
            'لو الباركود موجود عندك قبل كده، الصف بيحدّث أسعار النوع ده بدل ما يعمل صنف جديد.',
            'التصنيف أو الماركة اللي مش موجودين بيتعملوا لوحدهم.',
            'الكمية وسعر التكلفة = الرصيد الافتتاحي في الفرع اللي بتستورد منه (للأنواع اللي ملهاش رصيد هناك لسه).',
            'الموديلات تتكتب مفصولة بفاصلة، ويُفضّل بالماركة: Apple iPhone 13، Samsung Galaxy A54.',
        ] as $note) {
            $writer->addRow(Row::fromValues([$note]));
        }

        $writer->close();
    }

    /**
     * @return list<list<string|int|float>>
     */
    private function examples(): array
    {
        // name, category, brand, sku, variant, quality, barcode, retail, wholesale, technician, online, min stock, models, qty, cost
        return [
            ['سكرينة 9D', 'سكرينات حماية', '', 'SCR-9D', 'شفاف', '', '6221234567890', 150, 90, '', '', 5, 'Apple iPhone 13, Apple iPhone 14', 20, 45],
            ['سكرينة 9D', 'سكرينات حماية', '', '', 'مطفي', '', '6221234567891', 175, 110, '', '', 5, 'Apple iPhone 13, Apple iPhone 14', 12, 60],
            ['شاشة iPhone 11', 'شاشات', 'Apple', '', '', 'سيرفس باك', '', 2850, 2500, 2650, '', 2, 'Apple iPhone 11', 3, 2200],
            ['شاحن Anker 20W Type-C', 'شواحن', '', '', '', '', 'ANK-20W', 450, 380, '', 480, 3, '', '', ''],
        ];
    }
}
