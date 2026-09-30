<?php

declare(strict_types=1);

namespace App\Modules\Messaging\Support;

/**
 * The messages the shop can send on WhatsApp, their variables and built-in wording. The app fills
 * the variables; a line with a variable that has no value is left out.
 */
final class Templates
{
    private const HELLO = 'أهلاً أستاذ/ة {customer} 👋';

    private const TRACK = 'تابع جهازك من هنا: {link}';

    /**
     * @return array<string, array{label: string, group: string, variables: array<string, string>, body: string}>
     */
    public static function all(): array
    {
        $repair = [
            'customer' => 'اسم العميل',
            'device' => 'الجهاز',
            'ticket' => 'رقم التذكرة',
            'shop' => 'اسم المحل',
            'link' => 'لينك المتابعة',
            'expected' => 'ميعاد التسليم',
            'faults' => 'الأعطال',
            'cost' => 'التكلفة',
            'due' => 'المطلوب',
            'warranty' => 'الضمان لحد',
        ];

        return [
            'repair_received' => ['label' => 'استلام جهاز', 'group' => 'repairs', 'variables' => $repair, 'body' => implode("\n", [
                self::HELLO, 'استلمنا جهازك {device} في {shop} (تذكرة {ticket}).', 'ميعاد التسليم المتوقع: {expected}.', self::TRACK,
            ])],
            'repair_diagnosing' => ['label' => 'قيد الفحص', 'group' => 'repairs', 'variables' => $repair, 'body' => implode("\n", [
                self::HELLO, 'جهازك {device} دلوقتي قيد الفحص، هنكلمك أول ما نعرف العطل.', self::TRACK,
            ])],
            'repair_awaiting_approval' => ['label' => 'مستني موافقة العميل', 'group' => 'repairs', 'variables' => $repair, 'body' => implode("\n", [
                self::HELLO, 'فحصنا جهازك {device}: {faults}.', 'التكلفة {cost}. نبدأ؟ رد علينا بالموافقة.', self::TRACK,
            ])],
            'repair_repairing' => ['label' => 'جاري الإصلاح', 'group' => 'repairs', 'variables' => $repair, 'body' => implode("\n", [
                self::HELLO, 'بدأنا نصلّح جهازك {device}.', self::TRACK,
            ])],
            'repair_awaiting_part' => ['label' => 'مستني قطعة', 'group' => 'repairs', 'variables' => $repair, 'body' => implode("\n", [
                self::HELLO, 'جهازك {device} مستني قطعة غيار، هنبلّغك أول ما توصل.', self::TRACK,
            ])],
            'repair_ready' => ['label' => 'الجهاز جاهز', 'group' => 'repairs', 'variables' => $repair, 'body' => implode("\n", [
                self::HELLO, 'جهازك {device} جاهز للاستلام من {shop} ✅', 'المطلوب: {due}', 'متنساش تجيب إيصال الاستلام.', self::TRACK,
            ])],
            'repair_rejected' => ['label' => 'مش هينفع يتصلح', 'group' => 'repairs', 'variables' => $repair, 'body' => implode("\n", [
                self::HELLO, 'جهازك {device} مش هينفع يتصلح. تقدر تستلمه من {shop} في أي وقت.', self::TRACK,
            ])],
            'repair_delivered' => ['label' => 'بعد التسليم', 'group' => 'repairs', 'variables' => $repair, 'body' => implode("\n", [
                self::HELLO, 'شكراً لتعاملك مع {shop} 🌷', 'الضمان لحد {warranty}.',
            ])],
            'sale_receipt' => ['label' => 'فاتورة بيع', 'group' => 'sales', 'variables' => [
                'customer' => 'اسم العميل', 'shop' => 'اسم المحل', 'invoice' => 'رقم الفاتورة', 'total' => 'الإجمالي', 'link' => 'لينك الفاتورة',
            ], 'body' => "شكراً لتعاملك مع {shop} 🌷\nفاتورتك {invoice} بـ {total}\n{link}"],
            'supplier_return_note' => ['label' => 'إذن مرتجع لمورد', 'group' => 'suppliers', 'variables' => [
                'supplier' => 'اسم المورد', 'shop' => 'اسم المحل', 'note' => 'رقم الإذن', 'count' => 'عدد القطع', 'items' => 'الأصناف', 'total' => 'القيمة',
            ], 'body' => implode("\n", [
                'أهلاً أستاذ/ة {supplier} 👋',
                'معاك {shop}. جهّزنا إذن مرتجع {note} ({count} قطعة):',
                '{items}',
                'القيمة: {total}',
                'ياريت تقولنا إمتى نبعتهم أو تعدّي تستلمهم. شكراً 🙏',
            ])],
            'debt_reminder' => ['label' => 'تذكير بالحساب', 'group' => 'customers', 'variables' => [
                'customer' => 'اسم العميل', 'shop' => 'اسم المحل', 'balance' => 'المبلغ اللي عليه',
            ], 'body' => implode("\n", [
                'أهلاً أستاذ/ة {customer}،', 'حابين نفكّر حضرتك إن الحساب عندنا في {shop} عليه {balance}.', 'ياريت تعدّي علينا أو تحوّل على المحفظة / InstaPay في أقرب وقت. شكراً ليك 🙏',
            ])],
        ];
    }

    public static function exists(string $key): bool
    {
        return isset(self::all()[$key]);
    }

    public static function groupLabel(string $group): string
    {
        return match ($group) {
            'repairs' => 'الصيانة',
            'sales' => 'المبيعات',
            'suppliers' => 'الموردين',
            default => 'العملاء',
        };
    }
}
