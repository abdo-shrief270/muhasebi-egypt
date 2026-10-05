"""Composes a 1080x1920 reel from a recording (rec-*/log.json + frames) and a story spec, frame by
frame in Chromium (so Arabic text shapes properly), piped into ffmpeg (H.264, 30 fps)."""
import json, os, subprocess, sys
import imageio_ffmpeg
from playwright.sync_api import sync_playwright

HERE = os.path.dirname(os.path.abspath(__file__))
FPS = 30

ICONS = {
    'wifi-off': '<path d="M12 20h.01M8.5 16.4a5 5 0 0 1 7 0M5 12.9a10 10 0 0 1 5.2-2.7M19 12.9a10 10 0 0 0-2-1.5M2 8.8a15 15 0 0 1 4.2-2.6M22 8.8A15 15 0 0 0 10.7 5M2 2l20 20"/>',
    'cart': '<circle cx="8" cy="21" r="1"/><circle cx="19" cy="21" r="1"/><path d="M2.05 2.05h2l2.66 12.42a2 2 0 0 0 2 1.58h9.78a2 2 0 0 0 1.95-1.57l1.65-7.43H5.12"/>',
    'save': '<path d="M15.2 3a2 2 0 0 1 1.4.6l3.8 3.8a2 2 0 0 1 .6 1.4V19a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2z"/><path d="M17 21v-7a1 1 0 0 0-1-1H8a1 1 0 0 0-1 1v7M7 3v4a1 1 0 0 0 1 1h7"/>',
    'wifi': '<path d="M12 20h.01M2 8.82a15 15 0 0 1 20 0M5 12.86a10 10 0 0 1 14 0M8.5 16.43a5 5 0 0 1 7 0"/>',
    'check': '<path d="M20 6 9 17l-5-5"/>',
    'clock': '<circle cx="12" cy="12" r="10"/><path d="M12 6v6l4 2"/>',
}


def icon(name):
    return f'<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">{ICONS[name]}</svg>'


def stage_html(story):
    caps = ''
    for i, c in enumerate(story['captions']):
        sub = '<div class="s">' + c['sub'] + '</div>' if c.get('sub') else ''
        caps += (f'<div class="cap" id="cap{i}"><span class="ic {c.get("tone", "")}">{icon(c["icon"])}</span>'
                 f'<div><div class="t">{c["title"]}</div>{sub}</div></div>')
    return f'''<!doctype html><html><head><meta charset="utf-8"><link rel="stylesheet" href="fonts/fonts.css"><style>
*{{box-sizing:border-box;margin:0}}
html,body{{width:1080px;height:1920px;overflow:hidden;font-family:'Readex Pro';direction:rtl;color:#fff;background:#06191C}}
.bg{{position:absolute;inset:0;background:
  radial-gradient(900px 700px at 85% 5%, rgba(20,184,166,.45), transparent 62%),
  radial-gradient(800px 600px at 0% 100%, rgba(251,191,36,.13), transparent 60%),
  linear-gradient(160deg,#06191C 0%,#0A2A2F 55%,#0B3A40 100%)}}
.grid{{position:absolute;inset:0;background-image:linear-gradient(rgba(94,234,212,.06) 1px,transparent 1px),linear-gradient(90deg,rgba(94,234,212,.06) 1px,transparent 1px);background-size:60px 60px;-webkit-mask-image:radial-gradient(ellipse at 50% 30%,#000 10%,transparent 70%)}}
.brand{{position:absolute;top:150px;right:60px;display:flex;align-items:center;gap:14px;font-weight:700;font-size:38px}}
.brand img{{width:58px;height:58px}}
.cap{{position:absolute;top:250px;right:60px;left:60px;display:flex;align-items:center;gap:26px;opacity:0}}
.cap .ic{{flex:none;width:112px;height:112px;border-radius:30px;display:grid;place-items:center;background:rgba(94,234,212,.14);border:2px solid rgba(94,234,212,.4);color:#5EEAD4}}
.cap .ic.warn{{background:rgba(251,191,36,.14);border-color:rgba(251,191,36,.5);color:#FBBF24}}
.cap .ic.ok{{background:rgba(52,211,153,.18);border-color:rgba(52,211,153,.6);color:#34D399}}
.cap .ic svg{{width:60px;height:60px}}
.cap .t{{font-weight:700;font-size:78px;line-height:1.15}}
.cap .s{{margin-top:12px;font-size:36px;color:rgba(255,255,255,.75);line-height:1.4}}
.win{{position:absolute;left:40px;top:540px;width:1000px;height:1120px;border-radius:30px;overflow:hidden;background:#fff;
  box-shadow:0 50px 120px -20px rgba(0,0,0,.7),0 0 0 2px rgba(94,234,212,.25)}}
.win .bar{{height:44px;background:#EEF2F4;display:flex;align-items:center;gap:10px;padding:0 20px;direction:ltr}}
.win .bar i{{width:13px;height:13px;border-radius:50%;background:#F87171}}.win .bar i:nth-child(2){{background:#FBBF24}}.win .bar i:nth-child(3){{background:#34D399}}
.win .bar u{{margin-left:20px;height:24px;border-radius:12px;background:#fff;text-decoration:none;font:500 14px/24px 'Readex Pro';color:#64748B;padding:0 14px}}
.view{{position:absolute;top:44px;left:0;right:0;bottom:0;overflow:hidden}}
.view img{{position:absolute;top:0;left:0;width:2560px;height:1600px;transform-origin:0 0}}
.hook,.end{{position:absolute;inset:0;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;opacity:0}}
.hook .big{{font-weight:700;font-size:120px;line-height:1.2}}
.hook .big span,.end h1 span{{display:inline-block;padding-bottom:.12em;background:linear-gradient(90deg,#FBBF24,#5EEAD4 60%,#2DD4BF);-webkit-background-clip:text;background-clip:text;color:transparent}}
.hook .ic{{width:190px;height:190px;border-radius:52px;display:grid;place-items:center;background:rgba(251,191,36,.14);border:3px solid rgba(251,191,36,.5);color:#FBBF24;margin-bottom:60px}}
.hook .ic svg{{width:110px;height:110px}}
.end img{{width:200px;height:200px;filter:drop-shadow(0 20px 60px rgba(20,184,166,.6))}}
.end .n{{font-weight:700;font-size:96px;margin-top:30px}}
.end h1{{font-weight:700;font-size:70px;margin-top:40px;line-height:1.3}}
.end .cta{{margin-top:56px;padding:26px 60px;border-radius:999px;background:linear-gradient(180deg,#2DD4BF,#14B8A6);color:#042F2E;font-weight:700;font-size:52px;box-shadow:0 20px 60px -10px rgba(20,184,166,.8)}}
.end .url{{margin-top:36px;font-size:40px;color:rgba(255,255,255,.7);direction:ltr}}
</style></head><body>
<div class="bg"></div><div class="grid"></div>
<div id="main"><div class="brand"><img src="mark.svg">محاسبي</div>{caps}
<div class="win" id="win"><div class="bar"><i></i><i></i><i></i><u>app.muhasebi.com</u></div><div class="view"><img id="shot"></div></div></div>
<div class="hook" id="hook"><div class="ic">{icon(story['hook']['icon'])}</div><div class="big">{story['hook']['html']}</div></div>
<div class="end" id="end"><img src="mark.svg"><div class="n">محاسبي</div><h1>{story['end']['html']}</h1><div class="cta">جرّب ببلاش 14 يوم</div><div class="url">muhasebi.com</div></div>
</body></html>'''


def lerp(a, b, k): return a + (b - a) * k
def ease(k): return k * k * (3 - 2 * k)
def clamp(x): return max(0.0, min(1.0, x))


def camera(keys, r, aspect):
    """Keyframes [(r, x, y, w)] → the crop at recording time r, eased over 0.6 s before each key."""
    prev = keys[0]
    for k in keys:
        if r < k[0]:
            k_in = clamp((r - (k[0] - 0.6)) / 0.6)
            x, y, w = (lerp(prev[i], k[i], ease(k_in)) for i in (1, 2, 3))
            return x, y, w, w / aspect
        prev = k
    return prev[1], prev[2], prev[3], prev[3] / aspect


def main(story_path):
    story = json.load(open(story_path))
    rec = json.load(open(os.path.join(HERE, story['recording'], 'log.json')))
    t0 = rec['events']['start']
    frames = [(f['t'] - t0, f'{story["recording"]}/{f["i"]:05d}.jpg') for f in rec['frames']]
    ev = {k: v - t0 for k, v in rec['events'].items()}
    hook_len, end_len = story['hook']['len'], story['end']['len']
    rec_len = ev['end'] - story.get('trim_end', 0)
    total = hook_len + rec_len + end_len - 0.4
    open(os.path.join(HERE, '_stage.html'), 'w').write(stage_html(story))
    # caption windows: [start, end) in recording time, from event names or numbers
    def at(v): return ev[v] if isinstance(v, str) else v
    caps = [(at(c['from']) + c.get('delay', 0), at(c['to']) if c.get('to') is not None else rec_len) for c in story['captions']]
    keys = [(at(k[0]) + (k[4] if len(k) > 4 else 0), k[1], k[2], k[3]) for k in story['camera']]
    aspect = 1000 / 1076

    out = os.path.join(HERE, story['out'])
    ff = subprocess.Popen([imageio_ffmpeg.get_ffmpeg_exe(), '-y', '-loglevel', 'error', '-f', 'image2pipe', '-framerate', str(FPS),
                           '-i', '-', '-c:v', 'libx264', '-pix_fmt', 'yuv420p', '-crf', '18', '-preset', 'slow',
                           '-movflags', '+faststart', out], stdin=subprocess.PIPE)
    with sync_playwright() as p:
        b = p.chromium.launch(executable_path='/opt/pw-browsers/chromium-1194/chrome-linux/chrome')
        pg = b.new_page(viewport={'width': 1080, 'height': 1920})
        pg.goto('file://' + os.path.join(HERE, '_stage.html'))
        pg.evaluate('document.fonts.ready')
        last_src = None
        n = int(total * FPS)
        for i in range(n):
            T = i / FPS
            r = min(max(T - hook_len, 0), rec_len)
            src = frames[0][1]
            for ft, fp in frames:
                if ft <= r: src = fp
                else: break
            x, y, w, h = camera(keys, r, aspect)
            scale = 1000 / (w * 2)            # frames are 2x
            hook_o = 1 - clamp((T - (hook_len - 0.35)) / 0.35)
            end_o = clamp((T - (hook_len + rec_len - 0.4)) / 0.4)
            main_o = (1 - hook_o) * (1 - end_o)
            hook_s = 0.92 + 0.08 * ease(clamp(T / 0.5))
            cap_state = []
            for (a, z) in caps:
                o = clamp((r - a) / 0.3) * (1 - clamp((r - (z - 0.25)) / 0.25)) if T >= hook_len else 0
                dy = (1 - ease(clamp((r - a) / 0.35))) * 40
                cap_state.append((round(o, 3), round(dy, 1)))
            state = {'src': src if src != last_src else None, 'tx': -x * 2 * scale, 'ty': -y * 2 * scale, 'scale': scale,
                     'hook': hook_o, 'hookScale': hook_s, 'end': end_o, 'main': main_o, 'caps': cap_state,
                     'endScale': 0.94 + 0.06 * ease(clamp((T - (hook_len + rec_len - 0.4)) / 0.6))}
            pg.evaluate('''async s => {
              const img = document.getElementById('shot')
              if (s.src) { img.src = s.src; await img.decode() }
              img.style.transform = `translate(${s.tx}px,${s.ty}px) scale(${s.scale})`
              document.getElementById('main').style.opacity = s.main
              const h = document.getElementById('hook'); h.style.opacity = s.hook; h.style.transform = `scale(${s.hookScale})`
              const e = document.getElementById('end'); e.style.opacity = s.end; e.style.transform = `scale(${s.endScale})`
              s.caps.forEach(([o, dy], i) => { const c = document.getElementById('cap' + i); c.style.opacity = o; c.style.transform = `translateY(${dy}px)` })
            }''', state)
            last_src = src
            ff.stdin.write(pg.screenshot(type='png'))
            if i % 60 == 0: print(f'{i}/{n}', flush=True)
        b.close()
    ff.stdin.close(); ff.wait()
    print('wrote', out, round(total, 1), 's')


if __name__ == '__main__':
    main(sys.argv[1])
