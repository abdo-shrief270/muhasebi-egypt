"""Muhasebi print pieces: A4 tri-fold brochure (outside + inside), business card and the founders-offer
card (front + back). Print-ready PDFs with 3 mm bleed, plus PNG previews. HTML → Chromium.

Edit CONTACT below, then: python3 build.py
"""
import os
import segno
from playwright.sync_api import sync_playwright

HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'out')
os.makedirs(OUT, exist_ok=True)

CONTACT = {
    'name': 'عبد الرحمن شريف علي',
    'name_en': 'Abdelrahman Shrief Ali',
    'title': '',                       # e.g. 'المؤسس' — empty = not printed
    'phone': '0155 544 0882',          # the WhatsApp / call number as it should print
    'site': 'muhasebi.com',
}
OFFER = {'code': 'FOUNDERS50', 'text': 'خصم 50% أول 3 شهور', 'who': 'لأول 100 محل', 'until': 'العرض لحد 30 نوفمبر 2026'}
BLEED = 3  # mm


def qr(url, color='#0A2A2F'):
    return segno.make(url, error='m').svg_inline(scale=10, dark=color, light=None, border=0, omitsize=True)


QR_BROCHURE = qr('https://muhasebi.com/?utm_source=print&utm_medium=brochure&utm_campaign=field')
QR_CARD = qr('https://muhasebi.com/?utm_source=print&utm_medium=card&utm_campaign=field')
QR_OFFER = qr('https://muhasebi.com/?utm_source=print&utm_medium=offer_card&utm_campaign=founders50')

ICONS = {
    'cart': '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
    'wifi-off': '<path d="M12 20h.01M8.5 16.4a5 5 0 0 1 7 0M5 12.9a10 10 0 0 1 5.2-2.7M19 12.9a10 10 0 0 0-2-1.5M2 8.8a15 15 0 0 1 4.2-2.6M22 8.8A15 15 0 0 0 10.7 5M2 2l20 20"/>',
    'wrench': '<path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>',
    'scan': '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/>',
    'boxes': '<path d="M2.97 12.92A2 2 0 0 0 2 14.63v3.24a2 2 0 0 0 .97 1.71l3 1.8a2 2 0 0 0 2.06 0L12 19v-5.5l-5-3-4.03 2.42Z"/><path d="m7 16.5-4.74-2.85M7 16.5l5-3M7 16.5v5.17M12 13.5V19l3.97 2.38a2 2 0 0 0 2.06 0l3-1.8a2 2 0 0 0 .97-1.71v-3.24a2 2 0 0 0-.97-1.71L17 10.5l-5 3Z"/><path d="m17 16.5-5-3M17 16.5l4.74-2.85M17 16.5v5.17M7.97 4.42A2 2 0 0 0 7 6.13v4.37l5 3 5-3V6.13a2 2 0 0 0-.97-1.71l-3-1.8a2 2 0 0 0-2.06 0l-3 1.8Z"/><path d="M12 8 7.26 5.15M12 8l4.74-2.85M12 13.5V8"/>',
    'chart': '<path d="M3 3v18h18"/><path d="M7 16v-4M12 16V8M17 16v-7"/>',
    'wallet': '<path d="M19 7V4a1 1 0 0 0-1-1H5a2 2 0 0 0 0 4h15a1 1 0 0 1 1 1v4h-3a2 2 0 0 0 0 4h3a1 1 0 0 0 1-1v-2a1 1 0 0 0-1-1"/><path d="M3 5v14a2 2 0 0 0 2 2h15a1 1 0 0 0 1-1v-4"/>',
    'users': '<path d="M16 21v-2a4 4 0 0 0-4-4H6a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M22 21v-2a4 4 0 0 0-3-3.87M16 3.13a4 4 0 0 1 0 7.75"/>',
    'whatsapp': '<path d="M3 21l1.7-5A8.5 8.5 0 1 1 8 19.3L3 21"/>',
    'globe': '<circle cx="12" cy="12" r="10"/><path d="M12 2a14.5 14.5 0 0 0 0 20 14.5 14.5 0 0 0 0-20M2 12h20"/>',
    'facebook': '<path d="M18 2h-3a5 5 0 0 0-5 5v3H7v4h3v8h4v-8h3l1-4h-4V7a1 1 0 0 1 1-1h3z"/>',
    'phone': '<path d="M22 16.92v3a2 2 0 0 1-2.18 2 19.8 19.8 0 0 1-8.63-3.07 19.5 19.5 0 0 1-6-6A19.8 19.8 0 0 1 2.12 4.18 2 2 0 0 1 4.11 2h3a2 2 0 0 1 2 1.72c.13.96.36 1.9.7 2.81a2 2 0 0 1-.45 2.11L8.09 9.91a16 16 0 0 0 6 6l1.27-1.27a2 2 0 0 1 2.11-.45c.91.34 1.85.57 2.81.7A2 2 0 0 1 22 16.92z"/>',
    'store': '<path d="m2 7 4.41-4.41A2 2 0 0 1 7.83 2h8.34a2 2 0 0 1 1.42.59L22 7M4 12v8a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2v-8M15 22v-4a2 2 0 0 0-2-2h-2a2 2 0 0 0-2 2v4M2 7h20M22 7v3a2 2 0 0 1-2 2 2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 16 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 12 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 8 12a2.7 2.7 0 0 1-1.59-.63.7.7 0 0 0-.82 0A2.7 2.7 0 0 1 4 12a2 2 0 0 1-2-2V7"/>',
    'smartphone': '<rect width="14" height="20" x="5" y="2" rx="2"/><path d="M12 18h.01"/>',
    'truck': '<path d="M14 18V6a2 2 0 0 0-2-2H4a2 2 0 0 0-2 2v11a1 1 0 0 0 1 1h2M15 18H9M19 18h2a1 1 0 0 0 1-1v-3.65a1 1 0 0 0-.22-.62L18.3 9.38a1 1 0 0 0-.78-.38H14"/><circle cx="17" cy="18" r="2"/><circle cx="7" cy="18" r="2"/>',
    'ship': '<path d="M12 10.19V14M12 2v3M19 13V7a2 2 0 0 0-2-2H7a2 2 0 0 0-2 2v6M12 10.19 4.18 12.8a1 1 0 0 0-.65 1.2l1.54 5.37a1 1 0 0 0 .73.7 16 16 0 0 0 12.4 0 1 1 0 0 0 .73-.7l1.54-5.37a1 1 0 0 0-.65-1.2z"/><path d="M2 21c.6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1 .6.5 1.2 1 2.5 1 2.5 0 2.5-2 5-2 1.3 0 1.9.5 2.5 1"/>',
    'check': '<path d="M20 6 9 17l-5-5"/>',
    'shield': '<path d="M20 13c0 5-3.5 7.5-7.66 8.95a1 1 0 0 1-.67-.01C7.5 20.5 4 18 4 13V6a1 1 0 0 1 1-1c2 0 4.5-1.2 6.24-2.72a1.17 1.17 0 0 1 1.52 0C14.51 3.81 17 5 19 5a1 1 0 0 1 1 1z"/><path d="m9 12 2 2 4-4"/>',
}


def icon(name, cls='ic'):
    return f'<svg class="{cls}" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{ICONS[name]}</svg>'


BASE_CSS = '''
@page { margin: 0 }
* { box-sizing: border-box; margin: 0; padding: 0 }
html, body { font-family: 'Readex Pro'; direction: rtl; color: #0A2A2F; -webkit-print-color-adjust: exact; print-color-adjust: exact }
.ic { width: 1em; height: 1em; flex: none }
.grad { background: linear-gradient(90deg, #F59E0B, #14B8A6 60%, #0D9488); -webkit-background-clip: text; background-clip: text; color: transparent; display: inline-block; padding: .05em 0 .2em; line-height: 1.3 }
.dark { background:
  radial-gradient(70mm 60mm at 85% 10%, rgba(20,184,166,.45), transparent 62%),
  radial-gradient(60mm 50mm at 0% 100%, rgba(251,191,36,.14), transparent 60%),
  linear-gradient(160deg, #06191C 0%, #0A2A2F 55%, #0B3A40 100%); color: #fff }
.dark .grad { background: linear-gradient(90deg, #FBBF24, #5EEAD4 60%, #2DD4BF); -webkit-background-clip: text; background-clip: text; color: transparent }
.gridbg { position: absolute; inset: 0; background-image: linear-gradient(rgba(94,234,212,.07) .3mm, transparent .3mm), linear-gradient(90deg, rgba(94,234,212,.07) .3mm, transparent .3mm); background-size: 7mm 7mm; -webkit-mask-image: radial-gradient(ellipse at 60% 30%, #000 10%, transparent 70%) }
.shot { border-radius: 2.2mm; overflow: hidden; box-shadow: 0 2mm 6mm rgba(10,42,47,.18), 0 0 0 .25mm rgba(10,42,47,.08); background: #fff }
.shot img { display: block; width: 100%; height: 100%; object-fit: cover; object-position: top }
.shot .bar { height: 3.2mm; background: #EEF2F4; display: flex; gap: .9mm; align-items: center; padding: 0 2mm; direction: ltr }
.shot .bar i { width: 1.1mm; height: 1.1mm; border-radius: 50%; background: #F87171 } .shot .bar i:nth-child(2) { background: #FBBF24 } .shot .bar i:nth-child(3) { background: #34D399 }
'''


def page(w, h, body, css):
    """A sheet of w x h mm (trim size); the HTML page is that plus the bleed on every side."""
    W, H = w + 2 * BLEED, h + 2 * BLEED
    return f'''<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="fonts/fonts.css"><style>
@page {{ size: {W}mm {H}mm }}
{BASE_CSS}
html, body {{ width: {W}mm; height: {H}mm; overflow: hidden }}
@media print {{ html, body {{ height: auto; overflow: visible }} }}
.sheet {{ position: relative; width: {W}mm; height: {H}mm; overflow: hidden; background: #fff; break-after: page }}
{css}
</style></head><body><div class="sheet">{body}</div></body></html>'''


def shot(src, w, h, bar=True):
    b = '<div class="bar"><i></i><i></i><i></i></div>' if bar else ''
    return f'<div class="shot" style="width:{w}mm;height:{h}mm">{b}<img src="screens/{src}.webp" style="height:calc(100% - {3.2 if bar else 0}mm)"></div>'


# ---------- brochure: A4 landscape, folds at 99 / 198 mm ----------
PW = 99  # panel width (trim)

BRO_CSS = '''
.panel { position: absolute; top: 0; height: 216mm; padding: 12mm 9mm 10mm }
.p-l { left: 0; width: 102mm; padding-left: 12mm }       /* left panel takes the left bleed */
.p-m { left: 102mm; width: 99mm }
.p-r { left: 201mm; width: 102mm; padding-right: 12mm }  /* right panel takes the right bleed */
h2 { font-weight: 800; font-size: 17pt; line-height: 1.6 }
h3 { font-weight: 700; font-size: 10.5pt; line-height: 1.5 }
p, li { font-size: 8.6pt; line-height: 1.6; color: #3B5459 }
.dark p { color: rgba(255,255,255,.78) }
.kicker { display: inline-flex; align-items: center; gap: 1.6mm; font-weight: 600; font-size: 7.6pt; color: #0D9488; background: #E6FAF7; border: .25mm solid #99F6E4; border-radius: 99mm; padding: 1mm 3mm }
.dark .kicker { color: #99F6E4; background: rgba(94,234,212,.12); border-color: rgba(94,234,212,.35) }
.kicker:before { content: ''; width: 1.6mm; height: 1.6mm; border-radius: 50%; background: #F59E0B }
.feat { display: flex; gap: 3mm; margin-top: 4.2mm }
.feat .ib { width: 9mm; height: 9mm; border-radius: 2.6mm; background: #E6FAF7; color: #0D9488; display: grid; place-items: center; flex: none; font-size: 5mm }
.feat h3 { margin-bottom: .5mm }
.brand { display: flex; align-items: center; gap: 2.5mm; font-weight: 800; font-size: 15pt }
.brand img { width: 10mm; height: 10mm }
.cta { display: inline-flex; align-items: center; gap: 2mm; background: linear-gradient(180deg, #2DD4BF, #14B8A6); color: #042F2E; font-weight: 800; font-size: 11pt; border-radius: 99mm; padding: 2.6mm 6mm }
.foldnote { display: none }
'''


def brochure_outside():
    # Left → right: front cover | back (contact) | inner flap (who it's for). Folded, the cover faces up
    # and the flap is the first thing seen when it opens (Arabic opens from the left).
    who = [
        ('wrench', 'محلات الصيانة', 'استلام الأجهزة، المتابعة بالواتساب، قطع الغيار والضمان وعمولة الفني.'),
        ('store', 'محلات الإكسسوارات', 'كاشير بالباركود، مخزن لكل فرع، تعديل أسعار جماعي وطباعة ليبلات.'),
        ('smartphone', 'بيع الموبايلات', 'كل جهاز بالـ IMEI من الشراء للبيع، والمستعمل بفحصه وإقرار البيع.'),
        ('truck', 'تجار الجملة', 'أسعار جملة وفني، آجل بحد لكل عميل، وطلبات بين المحلات.'),
        ('ship', 'المستوردين', 'الشحنات وتكلفتها النهائية على كل صنف، وحسابات الموردين والوكلا.'),
    ]
    flap = ''.join(f'<div class="feat">{f"<div class=ib>{icon(i)}</div>"}<div><h3>{t}</h3><p>{d}</p></div></div>' for i, t, d in who)
    body = f'''
<div class="panel p-l dark"><div class="gridbg"></div>
  <div style="position:relative;height:100%;display:flex;flex-direction:column">
    <div class="brand"><img src="mark.svg">محاسبي</div>
    <div style="margin-top:20mm"><span class="kicker">برنامج محلات الموبايلات</span></div>
    <h1 style="margin-top:5mm;font-weight:800;font-size:27pt;line-height:1.55">محلك كله<br>في <span class="grad">برنامج واحد</span></h1>
    <p style="margin-top:4mm;font-size:9.6pt">كاشير، مخزن بالـ IMEI، صيانة، آجل وتقسيط، شحن وتحويلات، وتقارير بمكسبك الحقيقي.</p>
    <div style="margin-top:auto;position:relative;height:72mm">
      <div style="position:absolute;left:-14mm;bottom:8mm;transform:rotate(-4deg)">{shot('pos', 96, 60)}</div>
      <div style="position:absolute;right:0;bottom:0;width:30mm;height:64mm;border-radius:4.5mm;padding:1.3mm;background:#0B1416;box-shadow:0 3mm 8mm rgba(0,0,0,.5),0 0 0 .3mm rgba(94,234,212,.3)">
        <div style="width:100%;height:100%;border-radius:3.4mm;overflow:hidden"><img src="screens/m-dashboard.webp" style="width:100%;height:100%;object-fit:cover;object-position:top"></div></div>
    </div>
    <div style="margin-top:7mm"><span class="cta">جرّب ببلاش 14 يوم</span></div>
  </div>
</div>
<div class="panel p-m" style="background:#F6FAF9;display:flex;flex-direction:column">
  <span class="kicker">ابدأ النهارده</span>
  <h2 style="margin-top:3mm">سجّل محلك<br>في دقيقة</h2>
  <ol style="margin-top:5mm;list-style:none;display:flex;flex-direction:column;gap:3.2mm">
    <li style="display:flex;gap:3mm;align-items:center"><b style="width:7mm;height:7mm;border-radius:50%;background:#0D9488;color:#fff;display:grid;place-items:center;font-size:9pt;flex:none">1</b>امسح الـ QR وادخل على الموقع</li>
    <li style="display:flex;gap:3mm;align-items:center"><b style="width:7mm;height:7mm;border-radius:50%;background:#0D9488;color:#fff;display:grid;place-items:center;font-size:9pt;flex:none">2</b>اكتب اسم المحل ورقم موبايلك</li>
    <li style="display:flex;gap:3mm;align-items:center"><b style="width:7mm;height:7mm;border-radius:50%;background:#0D9488;color:#fff;display:grid;place-items:center;font-size:9pt;flex:none">3</b>ابدأ بيع على طول، من غير كارت</li>
  </ol>
  <div style="margin-top:8mm;align-self:center;background:#fff;border-radius:4mm;padding:5mm;box-shadow:0 1.5mm 5mm rgba(10,42,47,.1)">
    <div style="width:38mm;height:38mm">{QR_BROCHURE}</div></div>
  <p style="text-align:center;margin-top:2.5mm;font-weight:600;color:#0A2A2F">امسح الـ QR بكاميرا موبايلك</p>
  <div style="margin-top:auto;display:flex;flex-direction:column;gap:2.4mm;font-size:9pt;font-weight:600">
    <div style="display:flex;gap:2.5mm;align-items:center"><span style="color:#0D9488;font-size:4.6mm">{icon('globe')}</span><span dir="ltr">{CONTACT['site']}</span></div>
    <div style="display:flex;gap:2.5mm;align-items:center"><span style="color:#0D9488;font-size:4.6mm">{icon('whatsapp')}</span><span dir="ltr">{CONTACT['phone']}</span></div>
  </div>
</div>
<div class="panel p-r">
  <span class="kicker">لمين؟</span>
  <h2 style="margin-top:3mm">لكل نوع محل<br>موبايلات</h2>
  <p style="margin-top:2mm">نفس البرنامج، وكل محل بيفتح الأقسام اللي تناسب شغله بس.</p>
  {flap}
  <div style="margin-top:7mm;border-top:.3mm solid #D7E7E5;padding-top:5mm;display:flex;flex-direction:column;gap:2.2mm">
    {''.join(f'<div style="display:flex;gap:2.2mm;align-items:center;font-size:8.6pt;font-weight:600"><span style="color:#0D9488;font-size:4mm">{icon("check")}</span>{t}</div>' for t in ['على الكمبيوتر والموبايل والتابلت', 'نسخة احتياطية من بياناتك كل يوم', 'تجربة 14 يوم من غير كارت'])}
  </div>
</div>'''
    return page(297, 210, body, BRO_CSS)


def brochure_inside():
    # Read right → left: sell (right) → repairs & stock (middle) → money (left).
    def feats(items):
        return ''.join(f'<div class="feat"><div class="ib">{icon(i)}</div><div><h3>{t}</h3><p>{d}</p></div></div>' for i, t, d in items)
    sell = feats([
        ('scan', 'بيع بالباركود', 'امسح الصنف ينزل بسعره، والباقي محسوب لوحده. كاش أو فيزا أو محفظة أو InstaPay.'),
        ('wifi-off', 'شغال من غير نت', 'النت يقطع؟ كمّل بيع عادي، والفواتير بتتسجل لوحدها أول ما يرجع.'),
        ('users', 'العملاء والآجل والتقسيط', 'حساب لكل عميل بحد آجل، وتقسيط بمواعيد وتذكير على الواتساب.'),
        ('whatsapp', 'إيصال على الواتساب', 'اطبع الإيصال أو ابعته للعميل بلينك، وعليه QR يفتح الفاتورة.'),
    ])
    rep = feats([
        ('wrench', 'استلام جهاز في أقل من دقيقة', 'على شاشة لمس: الرقم، الجهاز، العطل، والسعر. والعميل يتابع جهازه برسالة واتساب.'),
        ('smartphone', 'IMEI لكل جهاز', 'تعرف كل جهاز اتشرى من مين واتباع لمين، والمستعمل بفحصه وصورة البطاقة.'),
        ('boxes', 'مخزن مظبوط', 'لكل فرع، بالتكلفة الحقيقية، وجرد، وتحويلات بين الفروع، ومرتجعات للموردين.'),
        ('store', 'متجر أونلاين لمحلك', 'أصنافك على لينك باسم محلك، والطلبات توصلك على البرنامج.'),
    ])
    money = feats([
        ('chart', 'مكسبك الحقيقي', 'المبيعات ناقص التكلفة ناقص المصاريف، يوم بيوم، وتطلعه Excel أو PDF.'),
        ('wallet', 'الدرج والوردية', 'كل جنيه داخل وخارج متسجل، وآخر الوردية تعرف الفرق على طول.'),
        ('shield', 'صلاحيات وأمان', 'كل موظف يشوف اللي يخصه بس، وكل عملية حساسة متسجلة باسم اللي عملها.'),
        ('smartphone', 'على الكمبيوتر والموبايل', 'تنزّله زي التطبيق، وتتابع محلك من أي مكان.'),
    ])
    body = f'''
<div class="panel p-r" style="display:flex;flex-direction:column">
  <span class="kicker">الكاشير</span>
  <h2 style="margin-top:3mm">بيع أسرع<br>وزحمة أقل</h2>
  {sell}
  <div style="margin-top:auto">{shot('pos', 81, 70)}</div>
</div>
<div class="panel p-m" style="background:#F6FAF9;display:flex;flex-direction:column">
  <span class="kicker">الصيانة والمخزن</span>
  <h2 style="margin-top:3mm">كل جهاز وكل قطعة<br>في مكانها</h2>
  {rep}
  <div style="margin-top:auto">{shot('repairs', 81, 70)}</div>
</div>
<div class="panel p-l" style="display:flex;flex-direction:column">
  <span class="kicker">الحسابات</span>
  <h2 style="margin-top:3mm">اعرف كسبت كام<br>بجد</h2>
  {money}
  <div style="margin-top:auto">{shot('report-sales', 81, 34)}</div>
  <div class="dark" style="margin-top:4mm;position:relative;border-radius:4mm;padding:5mm;overflow:hidden">
    <p style="font-size:8pt">الباقات تبدأ من</p>
    <p style="font-weight:800;font-size:20pt;color:#fff;line-height:1.6"><span class="grad">299 ج</span> في الشهر</p>
    <p style="font-size:8pt">شامل الضريبة · أول 14 يوم ببلاش · من غير عقود</p>
    <p style="font-size:8pt;margin-top:1.5mm">كل الباقات على <span dir="ltr" style="color:#5EEAD4;font-weight:600">muhasebi.com/pricing</span></p>
  </div>
</div>'''
    return page(297, 210, body, BRO_CSS)


# ---------- cards: 85 x 55 mm ----------
CARD_CSS = '''
.in { position: absolute; inset: 3mm; padding: 4.5mm 5mm }
'''


def card_front():
    body = f'''<div class="dark" style="position:absolute;inset:0"><div class="gridbg"></div>
  <div class="in" style="display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center">
    <img src="mark.svg" style="width:15mm;height:15mm;filter:drop-shadow(0 1.5mm 4mm rgba(20,184,166,.5))">
    <div style="font-weight:800;font-size:17pt;margin-top:2mm;line-height:1.5">محاسبي</div>
    <div style="font-size:7pt;letter-spacing:.35em;color:#5EEAD4;font-weight:600;direction:ltr;margin-top:1.6mm">MUHASEBI</div>
    <div style="font-size:7.4pt;color:rgba(255,255,255,.75);margin-top:2mm">برنامج محلات الموبايلات</div>
  </div></div>'''
    return page(85, 55, body, CARD_CSS)


def card_back():
    title = f'<div style="font-size:7.2pt;color:#5B7A7F">{CONTACT["title"]}</div>' if CONTACT['title'] else ''
    row = lambda ic, txt: f'<div style="display:flex;gap:1.8mm;align-items:center;font-size:7.6pt;font-weight:600"><span style="color:#0D9488;font-size:3.4mm">{icon(ic)}</span><span dir="ltr">{txt}</span></div>'
    body = f'''<div class="in" style="display:flex;gap:4mm;align-items:center">
  <div style="flex:1;display:flex;flex-direction:column;gap:1.6mm">
    <div style="font-weight:800;font-size:11pt;line-height:1.5">{CONTACT['name']}</div>
    <div style="font-size:6.6pt;color:#5B7A7F;direction:ltr;text-align:right;letter-spacing:.02em">{CONTACT['name_en']}</div>{title}
    <div style="height:.35mm;width:12mm;background:linear-gradient(90deg,#F59E0B,#14B8A6);margin:1mm 0"></div>
    {row('whatsapp', CONTACT['phone'])}{row('globe', CONTACT['site'])}
  </div>
  <div style="text-align:center"><div style="width:21mm;height:21mm">{QR_CARD}</div>
    <div style="font-size:5.6pt;color:#5B7A7F;margin-top:1mm">جرّب ببلاش 14 يوم</div></div>
</div>
<div style="position:absolute;left:0;right:0;bottom:0;height:6.5mm;background:linear-gradient(90deg,#0B4F58,#14B8A6)"></div>'''
    return page(85, 55, body, CARD_CSS)


def offer_front():
    body = f'''<div class="dark" style="position:absolute;inset:0"><div class="gridbg"></div>
  <div class="in" style="display:flex;flex-direction:column">
    <div style="display:flex;align-items:center;gap:1.8mm;font-weight:800;font-size:9pt"><img src="mark.svg" style="width:5.5mm;height:5.5mm">محاسبي</div>
    <div style="margin-top:auto;font-size:7.4pt;color:#FBBF24;font-weight:600">{OFFER['who']}</div>
    <div style="font-weight:800;font-size:17pt;line-height:1.55"><span class="grad">{OFFER['text'].split(' ', 2)[0]} {OFFER['text'].split(' ', 2)[1]}</span> {OFFER['text'].split(' ', 2)[2]}</div>
    <div style="margin-top:2.5mm;display:flex;align-items:center;gap:2mm">
      <span style="font-size:7pt;color:rgba(255,255,255,.75)">الكود</span>
      <span style="border:.35mm dashed #FBBF24;border-radius:1.6mm;padding:.8mm 2.6mm;font-weight:800;font-size:10pt;letter-spacing:.12em;direction:ltr">{OFFER['code']}</span>
    </div>
  </div></div>'''
    return page(85, 55, body, CARD_CSS)


def offer_back():
    steps = ['امسح الـ QR وسجّل محلك', 'جرّب كل حاجة ببلاش 14 يوم', 'وانت بتشترك اكتب كود الخصم']
    li = ''.join(f'<li style="display:flex;gap:1.8mm;align-items:center;font-size:7.2pt"><b style="width:4mm;height:4mm;border-radius:50%;background:#0D9488;color:#fff;display:grid;place-items:center;font-size:6pt;flex:none">{i + 1}</b>{s}</li>' for i, s in enumerate(steps))
    body = f'''<div class="in" style="display:flex;gap:4mm;align-items:center">
  <div style="flex:1">
    <div style="font-weight:800;font-size:9.5pt">إزاي تاخد العرض؟</div>
    <ol style="list-style:none;display:flex;flex-direction:column;gap:1.6mm;margin-top:2.2mm">{li}</ol>
    <div style="margin:1.8mm 6mm 0 0;font-weight:800;font-size:8.5pt;letter-spacing:.1em;direction:ltr;text-align:right;color:#0D9488">{OFFER['code']}</div>
    <div style="font-size:5.8pt;color:#5B7A7F;margin-top:1.8mm">{OFFER['until']} · <span dir="ltr">{CONTACT['site']}</span></div>
  </div>
  <div style="width:22mm;height:22mm">{QR_OFFER}</div>
</div>
<div style="position:absolute;left:0;right:0;bottom:0;height:6.5mm;background:linear-gradient(90deg,#0B4F58,#14B8A6)"></div>'''
    return page(85, 55, body, CARD_CSS)


PIECES = [
    ('brochure-a4-trifold', 297, 210, [brochure_outside, brochure_inside]),
    ('business-card-85x55', 85, 55, [card_front, card_back]),
    ('offer-card-85x55', 85, 55, [offer_front, offer_back]),
]


def main():
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome')
        for name, w, h, sides in PIECES:
            W, H = w + 2 * BLEED, h + 2 * BLEED
            htmls = [fn() for fn in sides]
            for i, doc in enumerate(htmls):
                path = os.path.join(HERE, f'_{name}-{i}.html')
                open(path, 'w').write(doc)
                pg = b.new_page(viewport={'width': round(W * 96 / 25.4), 'height': round(H * 96 / 25.4)}, device_scale_factor=4 if w < 100 else 2)
                pg.goto('file://' + path); pg.evaluate('document.fonts.ready'); pg.wait_for_timeout(400)
                side = ('outside', 'inside')[i] if name.startswith('brochure') else ('front', 'back')[i]
                pg.screenshot(path=os.path.join(OUT, f'{name}-{side}.png'))
                pg.close()
            # one PDF, one page per side: the second side's sheet appended to the first document
            body2 = htmls[1].split('<body>', 1)[1].rsplit('</body>', 1)[0]
            both = htmls[0].replace('</body></html>', body2 + '</body></html>')
            path = os.path.join(HERE, f'_{name}.html')
            open(path, 'w').write(both)
            pg = b.new_page()
            pg.goto('file://' + path); pg.evaluate('document.fonts.ready'); pg.wait_for_timeout(400)
            pg.pdf(path=os.path.join(OUT, f'{name}-print.pdf'), width=f'{W}mm', height=f'{H}mm', print_background=True, prefer_css_page_size=True)
            pg.close()
            print(name)
        b.close()


if __name__ == '__main__':
    main()
