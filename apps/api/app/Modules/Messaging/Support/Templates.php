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

        $order = [
            'customer' => 'اسم العميل',
            'shop' => 'اسم المحل',
            'order' => 'رقم الطلب',
            'items' => 'الأصناف',
            'total' => 'الإجمالي',
            'link' => 'لينك متابعة الطلب',
            'reason' => 'سبب الإلغاء',
        ];
        $follow = 'تابع طلبك من هنا: {link}';

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
            'online_order_confirmed' => ['label' => 'تأكيد الطلب', 'group' => 'online_store', 'variables' => $order, 'body' => implode("\n", [
                self::HELLO, 'طلبك {order} من {shop} اتأكد ✅', '{items}', 'الإجمالي: {total}', $follow,
            ])],
            'online_order_preparing' => ['label' => 'الطلب بيتجهّز', 'group' => 'online_store', 'variables' => $order, 'body' => implode("\n", [
                self::HELLO, 'بنجهّز طلبك {order} دلوقتي.', $follow,
            ])],
            'online_order_out_for_delivery' => ['label' => 'خرج للتوصيل', 'group' => 'online_store', 'variables' => $order, 'body' => implode("\n", [
                self::HELLO, 'طلبك {order} خرج للتوصيل 🛵', 'جهّز {total} للمندوب.', $follow,
            ])],
            'online_order_ready' => ['label' => 'جاهز للاستلام', 'group' => 'online_store', 'variables' => $order, 'body' => implode("\n", [
                self::HELLO, 'طلبك {order} جاهز تستلمه من {shop} ✅', 'المطلوب: {total}', $follow,
            ])],
            'online_order_delivered' => ['label' => 'بعد الاستلام', 'group' => 'online_store', 'variables' => $order, 'body' => implode("\n", [
                self::HELLO, 'شكراً لطلبك من {shop} 🌷', 'لو احتجت أي حاجة احنا موجودين.',
            ])],
            'online_order_cancelled' => ['label' => 'إلغاء الطلب', 'group' => 'online_store', 'variables' => $order, 'body' => implode("\n", [
                self::HELLO, 'آسفين، طلبك {order} من {shop} اتلغى.', 'السبب: {reason}', 'تقدر تطلب تاني أو تكلّمنا على الرقم ده.',
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
            'installment_reminder' => ['label' => 'تذكير بقسط', 'group' => 'customers', 'variables' => [
                'customer' => 'اسم العميل', 'shop' => 'اسم المحل', 'amount' => 'قيمة القسط', 'due' => 'ميعاد القسط', 'late' => 'التأخير', 'remaining' => 'الباقي من التقسيط', 'plan' => 'رقم التقسيط',
            ], 'body' => implode("\n", [
                'أهلاً أستاذ/ة {customer} 👋',
                'بنفكّر حضرتك إن قسط {amount} من تقسيط {plan} عند {shop} ميعاده {due}.',
                '{late}',
                'الباقي من التقسيط: {remaining}',
                'تقدر تدفع في المحل أو تحوّل على المحفظة / InstaPay. شكراً ليك 🙏',
            ])],
        ];
    }

    /** The owner's switch a template belongs to (off = it isn't sent), if any. */
    public static function feature(string $key): ?string
    {
        return match (true) {
            str_starts_with($key, 'repair_') => 'repairs.status_whatsapp',
            $key === 'sale_receipt' => 'sales.receipt_link',
            $key === 'debt_reminder' => 'customers.debt_reminders',
            $key === 'installment_reminder' => 'installments.reminders',
            default => null,
        };
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
            'online_store' => 'المتجر الأونلاين',
            default => 'العملاء',
        };
    }
}
