"""Muhasebi brand assets: the mark, logo lockups, Facebook profile picture and cover (HTML → PNG via Chromium)."""

def glyph(bars=('#fff', .5, .8), stroke='#fff', dot='#FBBF24', dy=-3):
    c, o1, o2 = bars
    return f'''<g transform="translate(0 {dy})">
  <rect x="21" y="52" width="10" height="16" rx="5" fill="{c}" fill-opacity="{o1}"/>
  <rect x="36" y="40" width="10" height="28" rx="5" fill="{c}" fill-opacity="{o2}"/>
  <path d="M77 37 V63 Q77 77 63 77 H26" fill="none" stroke="{stroke}" stroke-width="10" stroke-linecap="round"/>
  <circle cx="64.5" cy="37" r="12.5" fill="none" stroke="{stroke}" stroke-width="10"/>
  <circle cx="64.5" cy="37" r="4" fill="{dot}"/>
</g>'''

GRAD = '<linearGradient id="bg" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#14B8A6"/><stop offset="1" stop-color="#0B4F58"/></linearGradient>'
SVG = '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100">'
open('mark.svg', 'w').write(f'{SVG}<defs>{GRAD}</defs><rect width="100" height="100" rx="26" fill="url(#bg)"/>{glyph()}</svg>')
open('mark-dark.svg', 'w').write(f'{SVG}<rect width="100" height="100" rx="26" fill="#0A2A2F"/>{glyph(("#5EEAD4", .5, .8), "#2DD4BF")}</svg>')
open('glyph-white.svg', 'w').write(f'{SVG}{glyph()}</svg>')
open('glyph-teal.svg', 'w').write(f'{SVG}{glyph(("#0D9488", .5, .8), "#0D9488")}</svg>')

open('profile.html', 'w').write(f'''<!doctype html><html><head><meta charset="utf-8"><style>html,body{{margin:0}}</style></head><body>
<svg xmlns="http://www.w3.org/2000/svg" width="1080" height="1080" viewBox="0 0 100 100"><defs>{GRAD}
<radialGradient id="glow" cx=".3" cy=".2" r=".8"><stop offset="0" stop-color="#5EEAD4" stop-opacity=".5"/><stop offset="1" stop-color="#5EEAD4" stop-opacity="0"/></radialGradient></defs>
<rect width="100" height="100" fill="url(#bg)"/><rect width="100" height="100" fill="url(#glow)"/>
<g transform="translate(50 50) scale(.72) translate(-50 -50)">{glyph()}</g></svg></body></html>''')

FONT = '<link rel="stylesheet" href="fonts/fonts.css">'

def lockup(bg, word, latin, markfile, w=1400, h=480):
    return f'''<!doctype html><html><head><meta charset="utf-8">{FONT}<style>
html,body{{margin:0;width:{w}px;height:{h}px;background:{bg}}}
.l{{height:100%;display:flex;align-items:center;justify-content:center;gap:56px;direction:rtl}}
.l img{{width:260px;height:260px}}
.w{{font:700 168px/1 'Readex Pro';color:{word};letter-spacing:-.01em}}
.s{{font:600 44px/1 'Readex Pro';letter-spacing:.42em;color:{latin};margin-top:26px;direction:ltr;text-align:right;padding-right:6px}}
</style></head><body><div class="l"><img src="{markfile}"><div><div class="w">محاسبي</div><div class="s">MUHASEBI</div></div></div></body></html>'''

open('logo-light.html', 'w').write(lockup('#ffffff', '#0A2A2F', '#0D9488', 'mark.svg'))
open('logo-dark.html', 'w').write(lockup('#0A2A2F', '#ffffff', '#5EEAD4', 'mark-dark.svg'))
open('logo-transparent.html', 'w').write(lockup('transparent', '#0A2A2F', '#0D9488', 'mark.svg'))

# Facebook cover, 1640x924: phones show it all, desktop shows the middle 624px band
# (y 150–774) — everything that matters sits inside it, away from the bottom corners.
open('cover.html', 'w').write(f'''<!doctype html><html><head><meta charset="utf-8">{FONT}<style>
*{{box-sizing:border-box}}
html,body{{margin:0;width:1640px;height:924px;overflow:hidden;font-family:'Readex Pro'}}
.bg{{position:absolute;inset:0;background:
  radial-gradient(900px 600px at 78% 18%, rgba(20,184,166,.40), transparent 60%),
  radial-gradient(700px 500px at 8% 92%, rgba(251,191,36,.16), transparent 60%),
  linear-gradient(135deg,#06191C 0%,#0A2A2F 55%,#0B3A40 100%)}}
.grid{{position:absolute;inset:0;background-image:linear-gradient(rgba(94,234,212,.07) 1px,transparent 1px),linear-gradient(90deg,rgba(94,234,212,.07) 1px,transparent 1px);background-size:56px 56px;
  -webkit-mask-image:radial-gradient(ellipse at 30% 50%,#000 20%,transparent 70%)}}
.wm{{position:absolute;width:760px;height:760px;left:-120px;top:120px;opacity:.06}}
.copy{{position:absolute;right:110px;top:205px;width:660px;direction:rtl;color:#fff}}
.brand{{display:flex;align-items:center;gap:22px}}
.brand img{{width:96px;height:96px;filter:drop-shadow(0 10px 30px rgba(20,184,166,.45))}}
.brand .n{{font-weight:700;font-size:64px;line-height:1}}
.brand .l{{font-weight:600;font-size:18px;letter-spacing:.42em;color:#5EEAD4;margin-top:10px;direction:ltr;text-align:right}}
h1{{margin:44px 0 0;font-weight:700;font-size:76px;line-height:1.25;letter-spacing:-.01em}}
h1 span{{display:inline-block;padding:0 0 .18em;background:linear-gradient(90deg,#5EEAD4,#2DD4BF 40%,#FBBF24);-webkit-background-clip:text;background-clip:text;color:transparent}}
.sub{{margin-top:22px;font-size:27px;line-height:1.7;color:rgba(255,255,255,.72);font-weight:400}}
.tags{{margin-top:26px;display:flex;flex-wrap:wrap;gap:12px}}
.tags span{{padding:9px 20px;border-radius:999px;border:1px solid rgba(94,234,212,.35);background:rgba(94,234,212,.08);color:#CCFBF1;font-size:21px;font-weight:600}}
.cards{{position:absolute;left:90px;top:200px;width:740px;height:540px}}
.card{{position:absolute;border-radius:28px;background:rgba(255,255,255,.07);border:1px solid rgba(255,255,255,.14);
  backdrop-filter:blur(12px);box-shadow:0 30px 60px rgba(0,0,0,.35);color:#fff;direction:rtl;padding:26px 30px}}
.k{{font-size:19px;color:rgba(255,255,255,.65)}} .v{{font-size:46px;font-weight:700;margin-top:6px}}
.up{{display:inline-block;margin-top:6px;font-size:18px;font-weight:600;color:#0A2A2F;background:#5EEAD4;border-radius:999px;padding:3px 12px}}
.bars{{display:flex;align-items:flex-end;gap:12px;height:120px;margin-top:22px;direction:ltr}}
.bars i{{flex:1;border-radius:10px 10px 4px 4px;background:linear-gradient(#5EEAD4,#0D9488)}}
.bars i:last-child{{background:linear-gradient(#FBBF24,#F59E0B)}}
.row{{display:flex;justify-content:space-between;align-items:center;font-size:20px;margin-top:12px}}
.row b{{font-weight:600}} .pill{{font-size:16px;padding:4px 12px;border-radius:999px;background:rgba(94,234,212,.18);color:#5EEAD4;font-weight:600}}
.amber{{background:rgba(251,191,36,.18);color:#FBBF24}}
.dot{{width:12px;height:12px;border-radius:50%;background:#5EEAD4;box-shadow:0 0 0 6px rgba(94,234,212,.2);display:inline-block}}
</style></head><body>
<div class="bg"></div><div class="grid"></div>
<div class="cards">
  <div class="card" style="left:0;top:0;width:360px">
    <div class="k">المبيعات النهارده</div><div class="v">18,450 ج</div><span class="up">▲ 12% عن امبارح</span>
    <div class="bars"><i style="height:38%"></i><i style="height:55%"></i><i style="height:46%"></i><i style="height:70%"></i><i style="height:62%"></i><i style="height:100%"></i></div>
  </div>
  <div class="card" style="left:380px;top:40px;width:340px">
    <div class="k">صافي الربح الشهر ده</div><div class="v" style="font-size:40px">64,200 ج</div>
    <svg viewBox="0 0 260 70" style="width:100%;margin-top:14px"><defs><linearGradient id="a" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#5EEAD4" stop-opacity=".45"/><stop offset="1" stop-color="#5EEAD4" stop-opacity="0"/></linearGradient></defs>
      <path d="M0 58 C30 52 45 40 70 44 S110 26 140 30 S190 12 215 16 S250 6 260 4 V70 H0Z" fill="url(#a)"/>
      <path d="M0 58 C30 52 45 40 70 44 S110 26 140 30 S190 12 215 16 S250 6 260 4" fill="none" stroke="#5EEAD4" stroke-width="4" stroke-linecap="round"/>
      <circle cx="260" cy="4" r="6" fill="#FBBF24"/></svg>
  </div>
  <div class="card" style="left:40px;top:340px;width:420px;padding:22px 28px">
    <div class="row"><b>فاتورة #1048</b><span class="pill">اتدفعت ✓</span></div>
    <div class="row"><b>آجل على العملاء</b><span class="pill amber">12 عميل</span></div>
    <div class="row"><b>أصناف قربت تخلص</b><span class="pill amber">3 أصناف</span></div>
  </div>
  <div class="card" style="left:480px;top:300px;width:240px;padding:20px 24px">
    <div class="row" style="margin:0;gap:12px;justify-content:flex-start"><span class="dot"></span><b>متزامن</b></div>
    <div class="k" style="margin-top:8px;font-size:17px">شغّال حتى من غير نت</div>
  </div>
</div>
<div class="copy">
  <div class="brand"><img src="mark.svg"><div><div class="n">محاسبي</div><div class="l">MUHASEBI</div></div></div>
  <h1>شغلك كله…<br><span>في برنامج واحد</span></h1>
  <div class="sub">مبيعات، مخزون، حسابات، فريق وتقارير<br>بالعربي — على الكمبيوتر والموبايل</div>
  <div class="tags"><span>جرّب 14 يوم ببلاش</span><span>muhasebi.com</span></div>
</div>
</body></html>''')
