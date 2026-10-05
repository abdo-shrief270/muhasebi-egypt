"""Records a story from the demo shop: CDP screencast frames at 2x + an event log (rec-<name>/log.json).
Usage: python3 rec.py <scenario>   (scenarios below; each drives the app like a person would)."""
import base64, json, os, re, shutil, sys, time
from playwright.sync_api import sync_playwright

APP = 'http://localhost:3099'
RIPPLE = """
addEventListener('pointerdown', e => {
  const d = document.createElement('div');
  d.style.cssText = `position:fixed;left:${e.clientX-28}px;top:${e.clientY-28}px;width:56px;height:56px;border-radius:50%;
    background:rgba(13,148,136,.35);border:3px solid rgba(13,148,136,.9);z-index:2147483647;pointer-events:none;
    transform:scale(.4);opacity:1;transition:transform .45s ease-out,opacity .6s ease-out`;
  document.documentElement.appendChild(d);
  requestAnimationFrame(() => { d.style.transform = 'scale(1.6)'; d.style.opacity = '0' });
  setTimeout(() => d.remove(), 700);
}, true);
window.print = () => {};
"""


class Rec:
    def __init__(self, ctx, pg):
        self.ctx, self.pg, self.events = ctx, pg, {}

    def mark(self, name):
        self.events[name] = time.time(); print(name, flush=True)

    def wait(self, ms):   # keeps pumping CDP events
        end = time.time() + ms / 1000
        while time.time() < end: self.pg.wait_for_timeout(40)

    def tap(self, text, exact=False, last=False, scope='main button'):
        loc = self.pg.locator(scope).filter(has_text=re.compile('^' + re.escape(text) + '$')) if exact else self.pg.locator(scope, has_text=text)
        (loc.last if last else loc.first).click()


# ---------- scenarios ----------

def offline(r):
    pg, ctx = r.pg, r.ctx
    r.mark('start'); r.wait(1200)
    ctx.set_offline(True); r.mark('offline'); r.wait(2600)
    for name in ['اسكرينة زجاج 9D iPhone 13', 'باور بانك Anker 10000mAh', 'جراب Magsafe iPhone 14 Pro — كحلي']:
        r.tap(name, scope='button'); r.mark('tap:' + name); r.wait(900)
    r.mark('cart'); r.wait(1300)
    r.tap('دفع', last=True, scope='button'); r.mark('pay'); r.wait(1500)
    r.tap('تأكيد الدفع', scope='[role=dialog] button'); r.mark('receipt'); r.wait(3200)
    pg.keyboard.press('Escape'); r.mark('closed'); r.wait(1800)
    ctx.set_offline(False); pg.evaluate("dispatchEvent(new Event('online'))"); r.mark('online')
    for _ in range(60):
        r.wait(200)
        if pg.locator('text=مستنية').count() == 0: break
    r.mark('synced'); r.wait(3000)


def repairs(r):
    pg = r.pg
    r.mark('start'); r.wait(1000)
    r.mark('phone')
    for d in '01128639471':
        r.tap(d, exact=True); r.wait(190)
    r.wait(700)
    r.tap('التالي'); r.mark('name'); r.wait(700)
    pg.keyboard.type('محمد علي', delay=120); r.wait(600)
    r.tap('التالي'); r.mark('device'); r.wait(1300)
    r.tap('iPhone 11'); r.mark('fault'); r.wait(1300)
    r.tap('بتفصل بسرعة'); r.wait(500)
    r.tap('سخونية'); r.wait(700)
    r.tap('التالي'); r.mark('details'); r.wait(1100)
    r.tap('300', exact=True); r.wait(700)
    r.tap('بكرة'); r.mark('promise'); r.wait(700)
    r.tap('100', exact=True, last=True); r.mark('deposit'); r.wait(1000)
    r.tap('استلم واطبع'); r.mark('saved'); r.wait(3200)


def barcode(r):
    pg = r.pg
    r.mark('start'); r.wait(1200)
    box = pg.locator('input[placeholder*="الباركود"]').first
    for i, code in enumerate(['62210001000019', '62210001000082', '62210001000125']):
        box.click(); box.type(code, delay=18); pg.keyboard.press('Enter'); r.mark(f'scan{i}'); r.wait(1100)
    r.mark('cart'); r.wait(900)
    r.tap('دفع', last=True, scope='button'); r.mark('pay'); r.wait(1200)
    r.tap('1,000', scope='[role=dialog] button'); r.mark('cash'); r.wait(1500)
    r.tap('تأكيد الدفع', scope='[role=dialog] button'); r.mark('receipt'); r.wait(3500)


HILITE = '''(label) => {
  document.querySelectorAll('[data-reel-hi]').forEach(e => { e.style.outline = ''; e.style.boxShadow = ''; e.removeAttribute('data-reel-hi') })
  if (!label) return
  const el = [...document.querySelectorAll('main *')].find(e => e.children.length === 0 && e.textContent.trim() === label)
  let card = el; for (let i = 0; i < 4 && card; i++) { card = card.parentElement; if (card && card.offsetWidth > 200 && card.offsetHeight > 80) break }
  if (!card) return
  card.setAttribute('data-reel-hi', '1')
  card.style.transition = 'outline-color .3s, box-shadow .3s'
  card.style.outline = '3px solid #14B8A6'; card.style.boxShadow = '0 0 0 8px rgba(20,184,166,.18)'
}'''


def profit(r):
    pg = r.pg
    r.mark('start'); r.wait(1200)
    pg.evaluate(HILITE, 'صافي المبيعات'); r.mark('sales'); r.wait(2600)
    pg.evaluate(HILITE, 'مجمل الربح'); r.mark('gross'); r.wait(2800)
    pg.evaluate(HILITE, 'المصروفات'); r.mark('expenses'); r.wait(1500)
    pg.evaluate(HILITE, 'صافي الربح'); r.mark('net'); r.wait(2600)
    pg.evaluate(HILITE, None)
    pg.evaluate("window.scrollTo({top: 420, behavior: 'smooth'})"); r.mark('chart'); r.wait(3200)


def services(r):
    pg = r.pg
    r.mark('start'); r.wait(1500)
    amount = pg.locator('main input[type=number]').first
    amount.click(); r.mark('amount'); amount.type('2000', delay=160); r.mark('due'); r.wait(2200)
    pg.locator('main input[placeholder="01xxxxxxxxx"]').first.click(); pg.keyboard.type('01098765432', delay=55); r.wait(600)
    r.tap('سجّل إيداع'); r.mark('saved'); r.wait(2600)
    r.tap('سحب'); r.mark('withdraw'); r.wait(800)
    amount = pg.locator('main input[type=number]').first
    amount.click(); amount.type('500', delay=160); r.wait(1700)
    r.tap('سجّل سحب'); r.mark('saved2'); r.wait(3000)


SCENARIOS = {
    'services': ('/services', (1280, 900), services),
    'profit': ('/reports/sales', (1280, 800), profit),
    'barcode': ('/pos', (1280, 800), barcode),
    'offline': ('/pos', (1280, 800), offline),
    'repairs': ('/repairs/quick', (1280, 1000), repairs),
}


def main(name):
    path, (w, h), fn = SCENARIOS[name]
    out = f'rec-{name}'
    shutil.rmtree(out, ignore_errors=True); os.makedirs(out)
    frames = []
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--force-device-scale-factor=2'])
        ctx = b.new_context(viewport={'width': w, 'height': h}, device_scale_factor=2, storage_state='state.json')
        ctx.add_init_script(RIPPLE)
        pg = ctx.new_page()
        pg.goto(APP + path); pg.wait_for_timeout(3500)
        cdp = ctx.new_cdp_session(pg)

        def on_frame(f):
            n = len(frames)
            open(f'{out}/{n:05d}.jpg', 'wb').write(base64.b64decode(f['data']))
            frames.append({'i': n, 't': f['metadata']['timestamp']})
            try: cdp.send('Page.screencastFrameAck', {'sessionId': f['sessionId']})
            except Exception: pass
        cdp.on('Page.screencastFrame', on_frame)
        cdp.send('Page.startScreencast', {'format': 'jpeg', 'quality': 92, 'maxWidth': w * 2, 'maxHeight': h * 2})
        r = Rec(ctx, pg)
        fn(r)
        r.mark('end')
        cdp.send('Page.stopScreencast'); r.wait(300)
        b.close()
    json.dump({'frames': frames, 'events': r.events, 'size': [w, h]}, open(f'{out}/log.json', 'w'), ensure_ascii=False, indent=1)
    print(len(frames), 'frames', round(r.events['end'] - r.events['start'], 1), 's')


if __name__ == '__main__':
    main(sys.argv[1])
