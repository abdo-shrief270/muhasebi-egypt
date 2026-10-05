"""Records the offline-POS story from the demo shop: CDP screencast frames (2x) + an event log."""
import base64, json, os, shutil, time
from playwright.sync_api import sync_playwright

OUT = 'rec-offline'
shutil.rmtree(OUT, ignore_errors=True); os.makedirs(OUT)
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
"""
frames, events = [], {}
def mark(name): events[name] = time.time(); print(name)

with sync_playwright() as p:
    b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome', args=['--force-device-scale-factor=2'])
    ctx = b.new_context(viewport={'width': 1280, 'height': 800}, device_scale_factor=2, storage_state='state.json')
    ctx.add_init_script(RIPPLE)
    pg = ctx.new_page()
    pg.goto('http://localhost:3099/pos'); pg.wait_for_timeout(3500)
    cdp = ctx.new_cdp_session(pg)
    def on_frame(f):
        n = len(frames)
        open(f'{OUT}/{n:05d}.jpg', 'wb').write(base64.b64decode(f['data']))
        frames.append({'i': n, 't': f['metadata']['timestamp']})
        cdp.send('Page.screencastFrameAck', {'sessionId': f['sessionId']})
    cdp.on('Page.screencastFrame', on_frame)
    cdp.send('Page.startScreencast', {'format': 'jpeg', 'quality': 92, 'maxWidth': 2560, 'maxHeight': 1600, 'everyNthFrame': 1})
    def wait(ms):   # keep pumping CDP events while we wait
        end = time.time() + ms / 1000
        while time.time() < end: pg.wait_for_timeout(50)
    mark('start'); wait(1200)
    ctx.set_offline(True); mark('offline'); wait(2600)
    for name in ['اسكرينة زجاج 9D iPhone 13', 'باور بانك Anker 10000mAh', 'جراب Magsafe iPhone 14 Pro — كحلي']:
        pg.locator('button', has_text=name).first.click(); mark('tap:' + name); wait(900)
    mark('cart'); wait(1300)
    pg.locator('button', has_text='دفع').last.click(); mark('pay'); wait(1500)
    pg.locator('[role=dialog] button', has_text='تأكيد الدفع').click(); mark('receipt'); wait(3200)
    pg.keyboard.press('Escape'); mark('closed'); wait(1800)
    ctx.set_offline(False); pg.evaluate("dispatchEvent(new Event('online'))"); mark('online')
    # wait until the queued invoice is sent (the «مستنية» badge disappears)
    for _ in range(60):
        wait(200)
        if pg.locator('text=مستنية').count() == 0: break
    mark('synced'); wait(3000)
    mark('end')
    cdp.send('Page.stopScreencast'); wait(300)
    b.close()
json.dump({'frames': frames, 'events': events}, open(f'{OUT}/log.json', 'w'), ensure_ascii=False, indent=1)
print(len(frames), 'frames', round(events['end'] - events['start'], 1), 's')
