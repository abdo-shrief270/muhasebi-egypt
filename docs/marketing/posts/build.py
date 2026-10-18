"""Images (1080x1350) for the page posts that had only a raw screenshot or no picture. Same template as
../ads/build.py (its CSS, layouts and page()); run it from the same kind of working folder (screens/,
mark.svg, fonts/) with ../ads/build.py copied next to it as ads_build.py. Output PNGs land in out/."""
import os, sys

HERE = os.path.dirname(os.path.abspath(__file__))
src = open(os.path.join(HERE, 'ads_build.py')).read().split('only = sys.argv')[0]
ads = {'__file__': os.path.join(HERE, 'ads_build.py')}
exec(src, ads)
OUT = os.path.join(HERE, 'out')

POSTS = [
    dict(key='02-cash', eyebrow='الدرج والوردية', h1='الدرج ناقص؟<br><span>اعرف راح فين</span>',
         sub='كل فاتورة ومصروف باسم اللي عمله، والمتوقع قدام المعدود وقت التقفيل',
         desktop='cash', phone='m-owner', chip=('i-check', 'فرق الدرج <b class="num">0 ج</b>')),
    dict(key='04-question', eyebrow='سؤال لأصحاب المحلات', h1='بتعرف مكسبك<br><span>الحقيقي إمتى؟</span>',
         sub='كل يوم؟ آخر الشهر؟ ولا بتعدّ الدرج وتخمّن؟ قولنا في الكومنت',
         desktop='report-sales', phone='m-owner', chip=None),
    dict(key='08-commission', eyebrow='للصيانة', h1='عمولة الفني<br><span>محسوبة لوحدها</span>',
         sub='نسبة من المصنعية أو من المكسب أو مبلغ ثابت، وقت التسليم',
         desktop='repairs', phone='m-repairs', chip=('i-check', 'عمولة الفني <b class="num">450 ج</b>')),
    dict(key='12-used', eyebrow='المستعمل', h1='اشتري المستعمل<br><span>وانت مطمّن</span>',
         sub='البطاقة والسن، الـ IMEI، iCloud متشال، وإقرار بيع ممضي',
         desktop='used-devices', phone=None, chip=('i-check', 'iCloud متشال ✓')),
    dict(key='13-customers', eyebrow='حسابات العملاء', h1='كشكول الآجل؟<br><span>خلّيه على البرنامج</span>',
         sub='حد لكل عميل، كشف حساب، وتذكير على الواتساب بضغطة',
         desktop='customers', phone=None, chip=('i-wa', 'رسالة التذكير اتبعتت ✓')),
    dict(key='15-question', eyebrow='سؤال', h1='إيه أكتر حاجة<br><span>بتتعبك في المحل؟</span>',
         sub='المخزون؟ الصيانة؟ الدرج والحسابات؟ اكتبها في الكومنت',
         desktop='inventory', phone='m-pos', chip=None),
    dict(key='16-security', eyebrow='الأمان', h1='بياناتك<br><span>في أمان</span>',
         sub='نسخة احتياطية كل يوم، تحقق بخطوتين، وكل عملية حساسة متسجلة باسم اللي عملها',
         desktop='security', phone=None, chip=('i-check', 'التحقق بخطوتين شغال')),
    dict(key='18-invite', eyebrow='جيب محل صاحبك', h1='هو ياخد خصم<br><span>وانت تاخد رصيد</span>',
         sub='صاحبك خصم 20% أول 3 شهور، وانت 50 ج من اشتراكك أول ما يدفع',
         desktop='billing-rewards', phone=None, chip=None),
]

from playwright.sync_api import sync_playwright
os.makedirs(OUT, exist_ok=True)
w, h = ads['SIZES']['portrait']
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=ads['CHROME'])
    for c in POSTS:
        html = os.path.join(HERE, f'_{c["key"]}.html')
        open(html, 'w').write(ads['page'](c, 'portrait'))
        pg = browser.new_page(viewport={'width': w, 'height': h})
        pg.goto('file://' + html)
        pg.evaluate('document.fonts.ready')
        pg.wait_for_timeout(300)
        pg.screenshot(path=os.path.join(OUT, f'{c["key"]}-1080x1350.png'))
        pg.close()
        print(c['key'])
    browser.close()
