"""Muhasebi ad images: real app screenshots in brand frames, every concept in every ad size (HTML → PNG via Chromium).

Run it from a working folder holding: screens/ (copy of apps/site/public/screens), mark.svg (docs/brand)
and fonts/ (Readex Pro woff2 + fonts.css from Google Fonts). Output PNGs land in out/; the JPGs here
were saved from them at quality 90. `python3 build.py offer` renders only names containing "offer".
"""
import os, sys

CHROME = '/opt/pw-browsers/chromium-1194/chrome-linux/chrome'
HERE = os.path.dirname(os.path.abspath(__file__))
OUT = os.path.join(HERE, 'out')
os.makedirs(OUT, exist_ok=True)

SIZES = {
    'square': (1200, 1200),     # Facebook / Instagram feed, Google display square
    'portrait': (1080, 1350),   # Facebook / Instagram feed 4:5
    'story': (1080, 1920),      # Stories / Reels 9:16
    'landscape': (1200, 628),   # Google display, Facebook link ads
}

CTA = 'جرّبه ببلاش 14 يوم'

CONCEPTS = [
    dict(key='01-main', eyebrow='لمحلات الموبايلات والصيانة',
         h1='محلك كله<br>في <span>برنامج واحد</span>',
         sub='كاشير، مخزن بالـ IMEI، صيانة، آجل وتقسيط',
         desktop='pos', phone='m-dashboard',
         chip=('i-check', 'بيع بالباركود في ثانية')),
    dict(key='02-offline', eyebrow='الكاشير مبيقفش',
         h1='النت قطع؟<br><span>الكاشير شغال</span>',
         sub='بيع عادي، والفواتير بتتسجل لوحدها لما النت يرجع',
         desktop='offline-pos', phone='m-pos',
         chip=('i-wifi', 'أوفلاين — الفواتير محفوظة')),
    dict(key='03-repairs', eyebrow='للصيانة',
         h1='استلم جهاز الصيانة<br><span>في ثواني</span>',
         sub='والعميل يتابع جهازه وتوصله رسالة واتساب أول ما يجهز',
         desktop='repairs', phone='m-repairs',
         chip=('i-wa', 'جهازك جاهز للتسليم ✓')),
    dict(key='04-profit', eyebrow='تقارير واضحة',
         h1='مكسبك الحقيقي<br><span>كل يوم قدامك</span>',
         sub='المبيعات والمصاريف والمرتجعات وصافي الربح قدامك',
         desktop='report-sales', phone='m-owner',
         chip=('i-chart', 'صافي الربح <b class="num">35,625 ج</b>')),
    dict(key='05-imei', eyebrow='الموبايلات والمستعمل',
         h1='كل موبايل<br><span>متتبع بالـ IMEI</span>',
         sub='من الشراء للبيع، والمستعمل بفحصه وإقرار البيع',
         desktop='used-device', desktop_pos='100% 0', desktop_zoom=1.45, phone=None,
         chip=('i-scan', '<bdi dir="ltr">IMEI <b>358123451122335</b></bdi>')),
    dict(key='06-offer', eyebrow='عرض المؤسسين',
         h1='خصم <span>50%</span><br>لأول 100 محل',
         sub='أول 3 شهور بالكود — العرض لحد 30 نوفمبر',
         desktop='dashboard', phone='m-pos', code='FOUNDERS50',
         chip=None),
]

ICONS = {
    'i-check': '<path d="M20 6 9 17l-5-5"/>',
    'i-wifi': '<path d="M12 20h.01M8.5 16.4a5 5 0 0 1 7 0M5 12.9a10 10 0 0 1 5.2-2.7M19 12.9a10 10 0 0 0-2-1.5M2 8.8a15 15 0 0 1 4.2-2.6M22 8.8A15 15 0 0 0 10.7 5M2 2l20 20"/>',
    'i-wa': '<path d="M3 21l1.7-5A8.5 8.5 0 1 1 8 19.3L3 21"/><path d="M9 10a.5.5 0 0 0 1 0V9a.5.5 0 0 0-1 0v1a5 5 0 0 0 5 5h1a.5.5 0 0 0 0-1h-1a.5.5 0 0 0 0 1"/>',
    'i-chart': '<path d="M3 3v18h18"/><path d="M7 16v-4M12 16V8M17 16v-7"/>',
    'i-scan': '<path d="M3 7V5a2 2 0 0 1 2-2h2M17 3h2a2 2 0 0 1 2 2v2M21 17v2a2 2 0 0 1-2 2h-2M7 21H5a2 2 0 0 1-2-2v-2M7 12h10"/>',
}


def icon(name):
    return f'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round">{ICONS[name]}</svg>'


CSS = '''
*{box-sizing:border-box;margin:0}
html,body{width:var(--w);height:var(--h);overflow:hidden;font-family:'Readex Pro';direction:rtl;-webkit-font-smoothing:antialiased}
.num{font-variant-numeric:tabular-nums}
.bg{position:absolute;inset:0;background:
  radial-gradient(calc(var(--w)*.75) calc(var(--w)*.55) at 85% 8%, rgba(20,184,166,.42), transparent 62%),
  radial-gradient(calc(var(--w)*.6) calc(var(--w)*.45) at 5% 100%, rgba(251,191,36,.14), transparent 60%),
  linear-gradient(150deg,#06191C 0%,#0A2A2F 55%,#0B3A40 100%)}
.grid{position:absolute;inset:0;background-image:linear-gradient(rgba(94,234,212,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(94,234,212,.07) 1px,transparent 1px);background-size:56px 56px;
  -webkit-mask-image:radial-gradient(ellipse at 60% 35%,#000 15%,transparent 70%)}
.top{position:absolute;display:flex;align-items:center;justify-content:space-between;color:#fff}
.brand{display:flex;align-items:center;gap:.45em;font-weight:700}
.brand img{width:1.5em;height:1.5em;filter:drop-shadow(0 8px 24px rgba(20,184,166,.5))}
.domain{font-weight:500;color:rgba(255,255,255,.62);direction:ltr;letter-spacing:.02em}
.copy{position:absolute;color:#fff}
.eyebrow{display:inline-flex;align-items:center;gap:.5em;padding:.45em 1em;border-radius:999px;background:rgba(94,234,212,.12);border:1px solid rgba(94,234,212,.35);color:#99F6E4;font-weight:600}
.eyebrow:before{content:'';width:.5em;height:.5em;border-radius:50%;background:#FBBF24;box-shadow:0 0 12px #FBBF24}
h1{font-weight:700;line-height:1.22;letter-spacing:-.01em}
h1 span{display:inline-block;padding-bottom:.14em;background:linear-gradient(90deg,#FBBF24,#5EEAD4 55%,#2DD4BF);-webkit-background-clip:text;background-clip:text;color:transparent}
.sub{color:rgba(255,255,255,.74);line-height:1.6}
.cta{display:inline-flex;align-items:center;gap:.55em;padding:.62em 1.25em;border-radius:999px;background:linear-gradient(180deg,#2DD4BF,#14B8A6);color:#042F2E;font-weight:700;box-shadow:0 14px 40px -10px rgba(20,184,166,.8),inset 0 1px 0 rgba(255,255,255,.4)}
.cta svg{width:1.05em;height:1.05em;transform:scaleX(-1)}
.code{display:inline-flex;align-items:center;gap:.6em;padding:.5em 1em;border-radius:16px;border:2px dashed #FBBF24;color:#FDE68A;font-weight:700;background:rgba(251,191,36,.08)}
.code b{font-family:'Readex Pro';letter-spacing:.12em;color:#fff;direction:ltr}
.stage{position:absolute}
.win{position:absolute;border-radius:18px;overflow:hidden;background:#fff;box-shadow:0 40px 90px -20px rgba(0,0,0,.65),0 0 0 1px rgba(255,255,255,.08)}
.win .bar{height:34px;background:#EEF2F4;display:flex;align-items:center;gap:8px;padding:0 16px;direction:ltr}
.win .bar i{width:11px;height:11px;border-radius:50%;background:#CBD5DB}
.win .bar i:nth-child(1){background:#F87171}.win .bar i:nth-child(2){background:#FBBF24}.win .bar i:nth-child(3){background:#34D399}
.win .bar u{margin-left:18px;flex:1;max-width:46%;height:18px;border-radius:9px;background:#fff;text-decoration:none;font:500 11px/18px 'Readex Pro';color:#64748B;padding:0 10px}
.win .shot{width:100%;aspect-ratio:1.6;overflow:hidden}
.win .shot img{width:100%;height:100%;object-fit:cover}
.phone{position:absolute;border-radius:44px;padding:11px;background:#0B1416;box-shadow:0 40px 80px -18px rgba(0,0,0,.75),0 0 0 2px rgba(94,234,212,.25)}
.phone .scr{border-radius:34px;overflow:hidden;aspect-ratio:780/1688;background:#fff}
.phone .scr img{width:100%;height:100%;object-fit:cover;object-position:top}
.chip{position:absolute;display:flex;align-items:center;gap:.6em;padding:.7em 1.1em;border-radius:18px;background:rgba(255,255,255,.96);color:#0A2A2F;font-weight:600;box-shadow:0 24px 50px -12px rgba(0,0,0,.55);white-space:nowrap}
.chip .ic{width:1.9em;height:1.9em;border-radius:12px;display:grid;place-items:center;background:#CCFBF1;color:#0D9488}
.chip .ic svg{width:1.15em;height:1.15em}
.chip b{color:#0D9488}
'''

# Per-size geometry. Coordinates in px; RTL copy sits on the right.
LAYOUT = {
    'square': '''
.top{top:64px;right:72px;left:72px;font-size:34px}.domain{font-size:24px}
.copy{top:150px;right:72px;left:72px}
.eyebrow{font-size:22px}h1{font-size:82px;margin-top:26px}.sub{font-size:31px;margin-top:18px}
.actions{margin-top:30px;display:flex;gap:18px;align-items:center}.cta{font-size:30px}.code{font-size:26px}
.stage{left:0;right:0;top:700px;bottom:0}
.win{width:900px;left:60px;top:0}
.phone{width:250px;right:70px;top:-40px}
.chip{font-size:24px;left:260px;top:-40px}
''',
    'portrait': '''
.top{top:60px;right:64px;left:64px;font-size:32px}.domain{font-size:22px}
.copy{top:140px;right:64px;left:64px}
.eyebrow{font-size:21px}h1{font-size:76px;margin-top:24px}.sub{font-size:29px;margin-top:16px}
.actions{margin-top:28px;display:flex;gap:16px;align-items:center}.cta{font-size:28px}.code{font-size:24px}
.stage{left:0;right:0;top:700px;bottom:0}
.win{width:860px;left:40px;top:30px}
.phone{width:250px;right:56px;top:0}
.chip{font-size:23px;left:70px;top:-8px}
''',
    'story': '''
.top{top:220px;right:72px;left:72px;font-size:36px}.domain{font-size:24px}
.copy{top:330px;right:72px;left:72px}
.eyebrow{font-size:24px}h1{font-size:88px;margin-top:30px}.sub{font-size:34px;margin-top:20px}
.actions{margin-top:36px;display:flex;flex-direction:column;gap:22px;align-items:flex-start}.cta{font-size:34px}.code{font-size:28px}
.stage{left:0;right:0;top:960px;bottom:0}
.win{width:940px;left:-30px;top:100px}
.phone{width:300px;right:60px;top:0}
.chip{font-size:26px;left:60px;top:20px}
''',
    'landscape': '''
.top{top:40px;right:48px;left:48px;font-size:26px}.domain{font-size:17px}
.copy{top:112px;right:48px;width:540px}
.eyebrow{font-size:16px}h1{font-size:50px;margin-top:16px}.sub{font-size:20px;margin-top:10px}
.actions{margin-top:22px;display:flex;gap:12px;align-items:center}.cta{font-size:21px}.code{font-size:18px}
.stage{left:0;top:0;bottom:0;width:640px}
.win{width:600px;left:36px;top:130px}
.phone{width:170px;right:20px;top:96px;border-radius:32px;padding:8px}.phone .scr{border-radius:25px}
.chip{font-size:17px;left:40px;top:92px}
''',
}


def page(c, size):
    w, h = SIZES[size]
    zoom = c.get('desktop_zoom', 1)
    pos = c.get('desktop_pos', 'center top')
    shot_img = f'<img src="screens/{c["desktop"]}.webp" style="object-position:{pos};transform:scale({zoom});transform-origin:{pos}">'
    phone = ''
    if c.get('phone'):
        phone = f'<div class="phone"><div class="scr"><img src="screens/{c["phone"]}.webp"></div></div>'
    chip = ''
    if c.get('chip'):
        chip = f'<div class="chip"><span class="ic">{icon(c["chip"][0])}</span><span>{c["chip"][1]}</span></div>'
    code = f'<span class="code">الكود <b>{c["code"]}</b></span>' if c.get('code') else ''
    arrow = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14M13 6l6 6-6 6"/></svg>'
    return f'''<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="fonts/fonts.css">
<style>:root{{--w:{w}px;--h:{h}px}}{CSS}{LAYOUT[size]}</style></head><body>
<div class="bg"></div><div class="grid"></div>
<div class="top"><div class="brand"><img src="mark.svg">محاسبي</div><div class="domain">muhasebi.com</div></div>
<div class="copy"><span class="eyebrow">{c["eyebrow"]}</span><h1>{c["h1"]}</h1><p class="sub">{c["sub"]}</p>
<div class="actions"><span class="cta">{CTA}{arrow}</span>{code}</div></div>
<div class="stage"><div class="win"><div class="bar"><i></i><i></i><i></i><u>app.muhasebi.com</u></div><div class="shot">{shot_img}</div></div>{phone}{chip}</div>
</body></html>'''


only = sys.argv[1:]
from playwright.sync_api import sync_playwright
with sync_playwright() as p:
    browser = p.chromium.launch(executable_path=CHROME)
    for c in CONCEPTS:
        for size, (w, h) in SIZES.items():
            name = f'{c["key"]}-{size}-{w}x{h}'
            if only and not any(o in name for o in only):
                continue
            html = os.path.join(HERE, f'_{name}.html')
            open(html, 'w').write(page(c, size))
            pg = browser.new_page(viewport={'width': w, 'height': h})
            pg.goto('file://' + html)
            pg.evaluate('document.fonts.ready')
            pg.wait_for_timeout(300)
            pg.screenshot(path=os.path.join(OUT, name + '.png'))
            pg.close()
            print(name)
    browser.close()
