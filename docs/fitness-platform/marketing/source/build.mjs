// Tamreen marketing brochures: one 6-page A4 PDF per audience (gyms, coaches, trainees) and language (en, ar).
// Usage: node build.mjs [outDir] [audience] [lang]
// Needs (npm): puppeteer, lucide-static@0.460.0, @fontsource/ibm-plex-sans-arabic; system fonts Inter / Inter Display.
// Chromium path and flags come from pconf.json.
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath, pathToFileURL } from 'node:url';
import puppeteer from 'puppeteer';
import { brand, brochures, mockShared } from './content.mjs';

const here = path.dirname(fileURLToPath(import.meta.url));
const outDir = path.resolve(process.argv[2] || path.join(here, 'out'));
const onlyAudience = process.argv[3];
const onlyLang = process.argv[4];
fs.mkdirSync(outDir, { recursive: true });
const htmlDir = fs.mkdtempSync(path.join(os.tmpdir(), 'tamreen-brochures-'));

const nm = p => path.join(here, 'node_modules', p);
const icon = (name, size = 22, stroke = 2) => fs.readFileSync(nm(`lucide-static/icons/${name}.svg`), 'utf8')
  .replace(/<!--.*?-->/gs, '').replace(/\n\s*/g, ' ')
  .replace(/width="24"/, `width="${size}"`).replace(/height="24"/, `height="${size}"`)
  .replace(/stroke-width="2"/, `stroke-width="${stroke}"`);
const esc = s => String(s).replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
const fontUrl = w => pathToFileURL(nm(`@fontsource/ibm-plex-sans-arabic/files/ibm-plex-sans-arabic-arabic-${w}-normal.woff2`)).href;

// ---------- brand mark ----------
const logo = (lang, light = true) => `
<div class="logo ${light ? 'light' : ''}">
  <svg viewBox="0 0 40 40" width="34" height="34" aria-hidden="true">
    <rect x="1" y="1" width="38" height="38" rx="11" fill="${light ? '#fff' : 'var(--accent)'}"/>
    <path d="M7 22h6l3-7 5 14 4-10 2 3h6" fill="none" stroke="${light ? 'var(--accent)' : '#fff'}" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"/>
  </svg>
  <span>${esc(brand[lang])}</span>
</div>`;

// ---------- small drawings ----------
const lineChart = (pts, w = 200, h = 60) => {
  const xs = pts.map((_, i) => (i * (w - 10)) / (pts.length - 1) + 5);
  const min = Math.min(...pts), max = Math.max(...pts);
  const ys = pts.map(v => h - 8 - ((v - min) / (max - min || 1)) * (h - 18));
  const d = xs.map((x, i) => `${i ? 'L' : 'M'}${x.toFixed(1)},${ys[i].toFixed(1)}`).join(' ');
  return `<svg class="chart" viewBox="0 0 ${w} ${h}" preserveAspectRatio="none">
    <path d="${d} L${xs.at(-1)},${h} L${xs[0]},${h} Z" fill="var(--accent)" opacity=".12"/>
    <path d="${d}" fill="none" stroke="var(--accent)" stroke-width="2.4" stroke-linejoin="round" stroke-linecap="round"/>
    <circle cx="${xs.at(-1)}" cy="${ys.at(-1)}" r="3.6" fill="var(--accent)"/></svg>`;
};
const barChart = (vals, w = 200, h = 56) => {
  const bw = (w - 10) / vals.length, max = Math.max(...vals);
  return `<svg class="chart" viewBox="0 0 ${w} ${h}">${vals.map((v, i) => {
    const bh = (v / max) * (h - 6);
    return `<rect x="${5 + i * bw + bw * 0.18}" y="${h - bh}" width="${bw * 0.64}" height="${bh}" rx="2.5" fill="var(--accent)" opacity="${i === vals.length - 1 ? 1 : .35}"/>`;
  }).join('')}</svg>`;
};
const silhouette = shade => `<svg viewBox="0 0 60 80" class="sil"><rect width="60" height="80" rx="6" fill="${shade}"/><circle cx="30" cy="22" r="10" fill="#fff" opacity=".75"/><path d="M12 80c2-22 10-34 18-34s16 12 18 34z" fill="#fff" opacity=".75"/></svg>`;
// Decorative QR-like pattern (not a real code).
const fakeQr = (seed = 11, n = 25) => {
  let s = seed;
  const rnd = () => (s = (s * 16807) % 2147483647) / 2147483647;
  const finder = (x, y) => `<rect x="${x}" y="${y}" width="7" height="7"/><rect x="${x + 1}" y="${y + 1}" width="5" height="5" fill="#fff"/><rect x="${x + 2}" y="${y + 2}" width="3" height="3"/>`;
  const reserved = (x, y) => (x < 8 && y < 8) || (x >= n - 8 && y < 8) || (x < 8 && y >= n - 8);
  let cells = '';
  for (let y = 0; y < n; y++) for (let x = 0; x < n; x++) if (!reserved(x, y) && rnd() > 0.52) cells += `<rect x="${x}" y="${y}" width="1" height="1"/>`;
  return `<svg class="qrsvg" viewBox="-1.5 -1.5 ${n + 3} ${n + 3}" shape-rendering="crispEdges"><rect x="-1.5" y="-1.5" width="${n + 3}" height="${n + 3}" fill="#fff"/><g fill="#0b1f3a">${cells}${finder(0, 0)}${finder(n - 7, 0)}${finder(0, n - 7)}</g></svg>`;
};
const wave = () => `<svg class="wave" viewBox="0 0 60 14">${[5, 9, 12, 7, 10, 13, 6, 9, 11, 5, 8, 12, 7, 4, 9].map((h, i) => `<rect x="${i * 4}" y="${7 - h / 2}" width="2.2" height="${h}" rx="1.1"/>`).join('')}</svg>`;

// ---------- device frames & mockups ----------
const phone = inner => `<div class="phone"><div class="notch"></div><div class="screen"><div class="statusbar"><span>9:41</span><span>●●● ▮</span></div>${inner}</div></div>`;
const tablet = inner => `<div class="tablet"><div class="screen">${inner}</div></div>`;

const mockups = {
  gymDesk: m => tablet(`
    <div class="app-top"><b>${esc(m.deskTitle)}</b><span class="pill soft">${icon('building-2', 10)}${esc(m.branch)}</span></div>
    <div class="desk">
      <div class="desk-main">
        <div class="scan">${icon('scan-line', 22)}<span>${esc(m.scan)}</span></div>
        <div class="member">
          <div class="avatar">${esc(m.initials)}</div>
          <div class="mem-info"><b>${esc(m.member)}</b><span>${esc(m.plan)}</span><span class="muted">${esc(m.daysLeft)}</span></div>
          <div class="ok">${icon('check', 14, 3)}<span>${esc(m.allowed)}</span></div>
        </div>
        <div class="note">${icon('bell-ring', 12)}<span>${esc(m.renewSoon)}</span></div>
      </div>
      <div class="desk-side">
        <div class="kpi big"><span>${esc(m.occupancy)}</span><b class="num">42</b></div>
        <div class="list-title">${esc(m.recent)}</div>
        ${m.names.map((n, i) => `<div class="row"><span>${esc(n)}</span><span class="num muted">${m.times[i]}</span></div>`).join('')}
      </div>
    </div>`),
  gymDash: m => phone(`
    <div class="app-top"><b>${esc(m.dashTitle)}</b>${icon('layout-dashboard', 13)}</div>
    <div class="kpis">
      <div class="kpi"><span>${esc(m.revenue)}</span><b class="num">12,450</b><small>${esc(m.currency)}</small></div>
      <div class="kpi"><span>${esc(m.checkins)}</span><b class="num">168</b></div>
      <div class="kpi"><span>${esc(m.renewals)}</span><b class="num">14</b></div>
      <div class="kpi"><span>${esc(m.pt)}</span><b class="num">11</b></div>
    </div>
    <div class="card-mini">${barChart([22, 30, 26, 35, 41, 38, 48])}</div>
    <div class="list-title">${esc(m.expiring)}</div>
    ${m.expNames.map((n, i) => `<div class="row"><span>${esc(n)}</span><span class="tag-mini">${esc(m.expDays[i])}</span></div>`).join('')}`),
  memberApp: m => phone(`
    <div class="app-top"><b>${esc(m.memberTitle)}</b>${icon('id-card', 13)}</div>
    <div class="memcard big">
      <div class="mc-text"><small>${esc(m.card)}</small><b>${esc(m.gymName)}</b><span>${esc(m.valid)}</span></div>
      ${fakeQr()}
      <span class="qr-hint">${esc(m.showQr)}</span>
    </div>
    <div class="list-title">${esc(m.nextClass)}</div>
    <div class="ex"><div class="ex-icon">${icon('calendar-check', 12)}</div><div class="ex-body"><b>${esc(m.className)}</b><small>${esc(m.spots)}</small></div></div>
    <div class="btn">${esc(m.book)}</div>`),
  trainerStatement: m => tablet(`
    <div class="app-top"><b>${esc(m.stTitle)}</b><span class="pill soft">${esc(m.stMonth)}</span></div>
    <div class="st">
      <div class="st-head"><div class="avatar sm">${esc(m.stInitials)}</div><div><b>${esc(m.stTrainer)}</b><small>${esc(m.stRule)}</small></div></div>
      <table class="bt">${m.stRows.map(r => `<tr><td>${esc(r[0])}</td><td class="num end">${esc(r[1])}</td></tr>`).join('')}
        <tr class="total"><td>${esc(m.stTotal)}</td><td class="num end">12,120</td></tr></table>
      <div class="st-foot"><span class="note">${icon('clock', 11)}<span>${esc(m.stStatus)}</span></span><span class="btn sm">${icon('check', 11, 3)}<span>${esc(m.stApprove)}</span></span></div>
    </div>`),
  coachPhone: m => phone(`
    <div class="app-top"><b>${esc(m.appToday)}</b><span class="pill soft">${esc(m.week)}</span></div>
    <div class="day-title">${esc(m.day)}</div>
    ${m.ex.map((e, i) => `<div class="ex ${i === 0 ? 'active' : ''}"><div class="ex-icon">${icon('dumbbell', 12)}</div><div class="ex-body"><b>${esc(e.name)}</b><span class="num">${esc(e.sets)}</span><small>${esc(e.last)}</small></div></div>`).join('')}
    <div class="rest">${icon('timer', 12)}<span>${esc(m.rest)}</span></div>
    <div class="btn">${esc(m.log)}</div>`),
  coachBuilder: m => tablet(`
    <div class="app-top"><b>${esc(m.builderTitle)}</b><span class="pill soft">${esc(m.progName)}</span></div>
    <div class="tabs">${m.weekTabs.map((w, i) => `<span class="${i === 0 ? 'on' : ''}">${esc(w)}</span>`).join('')}</div>
    <div class="day-title">${esc(m.dayName)}</div>
    <table class="bt grid"><tr>${m.cols.map(c => `<th>${esc(c)}</th>`).join('')}</tr>
      ${m.rows.map(r => `<tr><td>${icon('grip-vertical', 9)}<span>${esc(r[0])}</span></td>${r.slice(1).map(x => `<td><span class="num">${esc(x)}</span></td>`).join('')}</tr>`).join('')}</table>
    <div class="bt-actions"><span class="btn ghost sm">${esc(m.template)}</span><span class="btn sm">${esc(m.assign)}</span></div>`),
  coachReview: m => tablet(`
    <div class="app-top"><b>${esc(m.queueTitle)}</b><span class="pill">${esc(m.due)}</span></div>
    <div class="review">
      <div class="photos">
        <div class="ph">${silhouette('#c9d3df')}<span>${esc(m.before)}</span></div>
        <div class="ph">${silhouette('var(--accent)')}<span>${esc(m.after)}</span></div>
      </div>
      <div class="rev-side">
        <div class="client"><div class="avatar sm">${esc(m.revInitials)}</div><b>${esc(m.client)}</b></div>
        <div class="metric"><span>${esc(m.weight)}</span><b class="num">${esc(m.weightDelta)}</b></div>
        ${lineChart([78.6, 78.1, 77.9, 77.2, 76.8, 76.1, 75.2], 200, 50)}
        <div class="metric"><span>${esc(m.adherence)}</span><b class="num">92%</b></div>
        <div class="btn sm">${icon('mic', 11)}<span>${esc(m.voice)}</span></div>
      </div>
    </div>
    <div class="list-title">${esc(m.nextUp)}</div>
    <div class="queue">${m.queue.map((q, i) => `<div class="row"><span class="qname"><span class="avatar xs">${esc(q[0].slice(0, 1))}</span>${esc(q[0])}</span><span class="muted">${esc(q[1])}</span><b class="num">${esc(q[2])}</b></div>`).join('')}</div>`),
  coachChat: m => phone(`
    <div class="chat-top"><div class="avatar sm">${esc(m.chatInitials)}</div><div><b>${esc(m.chatTitle)}</b><small>${esc(m.online)}</small></div></div>
    <div class="bubbles">${m.msgs.map(x => {
      const side = x.me ? 'me' : 'them';
      if (x.voice) return `<div class="bub ${side} voice">${icon('play', 10)}${wave()}<span class="num">${x.voice}</span></div>`;
      if (x.photo) return `<div class="bub ${side} img"><div class="food">${icon('salad', 22, 1.6)}</div><span>${esc(x.photo)}</span></div>`;
      return `<div class="bub ${side}">${esc(x.text)}</div>`;
    }).join('')}</div>
    <div class="composer"><span>${esc(m.input)}</span>${icon('mic', 12)}</div>`),
  traineeLogger: m => phone(`
    <div class="app-top"><b>${esc(m.logger)}</b><span class="pill soft">${icon('wifi-off', 9)}${esc(m.offline)}</span></div>
    <div class="day-title">${esc(m.exercise)}</div>
    <div class="sets">
      <div class="set-row head"><span>${esc(m.set)}</span><span>${esc(m.kg)}</span><span>${esc(m.reps)}</span><span></span></div>
      ${[[1, 80, 8, 1], [2, 90, 6, 1], [3, 100, 5, 1], [4, 100, 5, 0]].map(([s, k, r, d]) => `<div class="set-row ${d ? 'done' : ''}"><span class="num">${s}</span><span class="num">${k}</span><span class="num">${r}</span><span>${d ? icon('check', 11, 3) : ''}</span></div>`).join('')}
    </div>
    <div class="timer-ring"><svg viewBox="0 0 80 80"><circle cx="40" cy="40" r="32" fill="none" stroke="#e5ebf2" stroke-width="7"/><circle cx="40" cy="40" r="32" fill="none" stroke="var(--accent)" stroke-width="7" stroke-linecap="round" stroke-dasharray="201" stroke-dashoffset="70" transform="rotate(-90 40 40)"/></svg><div><b class="num">01:12</b><span>${esc(m.restTimer)}</span></div></div>`),
  traineeProgress: m => phone(`
    <div class="app-top"><b>${esc(m.progress)}</b>${icon('trending-up', 13)}</div>
    <div class="pr">${icon('star', 12)}<span>${esc(m.pr)}</span></div>
    <div class="card-mini"><div class="metric"><span>${esc(m.weightTrend)}</span><b class="num">${esc(m.trendDelta)}</b></div>${lineChart([84, 83.5, 83.6, 82.4, 81.9, 81.1, 80.3, 79.9], 200, 54)}</div>
    <div class="card-mini"><div class="metric"><span>${esc(m.volume)}</span><b class="num">+12%</b></div>${barChart([18, 21, 20, 24, 23, 27], 200, 40)}</div>
    <div class="memcard">
      <div class="mc-text"><small>${esc(m.card)}</small><b>${esc(m.gymName)}</b><span>${esc(m.valid)}</span></div>
      <div class="qr-sm">${fakeQr(5, 21)}</div>
    </div>`),
};

// ---------- page builders ----------
const TOTAL = 6;
const footer = (c, t, n) => `
<div class="footer"><div class="f-brand">${logo(c.lang, false)}<span class="muted">${esc(t.tag)}</span></div>
<div class="muted"><span dir="ltr">${esc(brand.site)}</span> · <span class="num">${n} / ${TOTAL}</span></div></div>`;
const tagHtml = (t, kind) => kind ? `<span class="tag ${kind}">${esc(t.tags[kind])}</span>` : '';
const fcard = (t, it) => `
<div class="fcard">
  <div class="ficon">${icon(it.icon, 19)}</div>
  <div class="fbody"><h4>${esc(it.title)} ${tagHtml(t, it.tag)}</h4><p>${esc(it.text)}</p></div>
</div>`;
const laterGroups = (t, groups = []) => groups.map(g => `
<div class="later"><b>${icon(g.kind === 'soon' ? 'clock' : 'rocket', 14)}${esc(g.title)}</b><div class="chips">${g.items.map(x => `<span class="chip ${g.kind}">${esc(x)}</span>`).join('')}</div></div>`).join('');

function pages(c, t) {
  const P = [];
  P.push(`<section class="page cover">
    <div class="deco d1"></div><div class="deco d2"></div><div class="deco d3"></div>
    <div class="cover-top">${logo(c.lang)}<span class="badge">${icon('sparkles', 13)}${esc(t.badge)}</span></div>
    <div class="cover-text">
      <span class="eyebrow">${esc(t.tag)}</span>
      <h1>${esc(t.headline)}</h1>
      <p class="lead">${esc(t.sub)}</p>
    </div>
    <div class="cover-art n${t.coverCount}">${t.coverArt}</div>
    <div class="cover-strip">${t.highlights.map(h => `<div>${icon(h.icon, 16)}<span>${esc(h.text)}</span></div>`).join('')}</div>
  </section>`);
  P.push(`<section class="page pad">
    <span class="eyebrow dark">${esc(t.why.eyebrow)}</span>
    <h2>${esc(t.why.title)}</h2>
    <p class="intro">${esc(t.why.intro)}</p>
    <div class="why">
      <div class="col pains"><h3>${icon('x', 17, 2.6)}${esc(t.why.painsTitle)}</h3>${t.why.pains.map(p => `<div class="li">${icon('circle-alert', 15)}<span>${esc(p)}</span></div>`).join('')}</div>
      <div class="col gains"><h3>${icon('check', 17, 2.6)}${esc(t.why.gainsTitle)}</h3>${t.why.gains.map(p => `<div class="li">${icon('circle-check', 15)}<span>${esc(p)}</span></div>`).join('')}</div>
    </div>
    <div class="statement"><div class="st-icon">${icon(t.why.statementIcon, 24)}</div><p>${esc(t.why.statement)}</p></div>
    <h3 class="sub-h">${esc(t.who.title)}</h3>
    <div class="who">${t.who.items.map(w => `<div class="wcard"><div class="ficon">${icon(w.icon, 19)}</div><h4>${esc(w.title)}</h4><p>${esc(w.text)}</p></div>`).join('')}</div>
    ${footer(c, t, 2)}
  </section>`);
  [t.f1, t.f2].forEach((f, i) => {
    P.push(`<section class="page pad">
      <span class="eyebrow dark">${esc(f.eyebrow)}</span>
      <h2>${esc(f.title)}</h2>
      <p class="intro">${esc(f.intro)}</p>
      <div class="fgrid">${f.items.map(it => fcard(t, it)).join('')}</div>
      ${laterGroups(t, f.later)}
      <div class="showcase n${f.artCount}">${f.art}<p class="caption">${esc(f.caption)}</p></div>
      ${footer(c, t, 3 + i)}
    </section>`);
  });
  P.push(`<section class="page pad">
    <span class="eyebrow dark">${esc(t.how.eyebrow)}</span>
    <h2>${esc(t.how.title)}</h2>
    <div class="steps">${t.how.steps.map((s, i) => `<div class="step"><div class="num-badge num">${i + 1}</div><h4>${esc(s.title)}</h4><p>${esc(s.text)}</p></div>`).join('')}</div>
    <div class="band"><h3>${icon(t.band.icon, 17)}${esc(t.band.title)}</h3><div class="chips big">${t.band.items.map(x => `<span class="chip">${esc(x)}</span>`).join('')}</div></div>
    <h3 class="sub-h">${esc(t.trust.title)}</h3>
    <div class="tgrid">${t.trust.items.map(it => `<div class="tcard"><div class="ficon">${icon(it.icon, 18)}</div><div><h4>${esc(it.title)}</h4><p>${esc(it.text)}</p></div></div>`).join('')}</div>
    <h3 class="sub-h faq-h">${esc(t.faq.title)}</h3>
    <div class="faq">${t.faq.items.map(f => `<div class="qa"><h4>${icon('circle-help', 15)}<span>${esc(f.q)}</span></h4><p>${esc(f.a)}</p></div>`).join('')}</div>
    ${t.trust.note ? `<div class="notice">${icon('heart-pulse', 16)}<p>${esc(t.trust.note)}</p></div>` : ''}
    ${footer(c, t, 5)}
  </section>`);
  P.push(`<section class="page pad">
    <span class="eyebrow dark">${esc(t.plans.eyebrow)}</span>
    <h2>${esc(t.plans.title)}</h2>
    <p class="intro">${esc(t.plans.intro)}</p>
    <div class="plans n${t.plans.items.length}">${t.plans.items.map(p => `
      <div class="plan ${p.highlight ? 'hl' : ''}">
        ${p.ribbon ? `<span class="ribbon">${esc(p.ribbon)}</span>` : ''}
        <h4>${esc(p.name)}</h4>
        <div class="price"><b class="${/\d/.test(p.price) ? 'num' : ''}">${esc(p.price)}</b><span>${esc(p.period)}</span></div>
        <div class="limit">${esc(p.limit)}</div>
        <ul>${p.features.map(x => `<li>${icon('check', 12, 3)}<span>${esc(x)}</span></li>`).join('')}</ul>
      </div>`).join('')}
    </div>
    <div class="included"><b>${esc(t.plans.includedTitle)}</b><div class="inc-grid">${t.plans.included.map(x => `<span>${icon('circle-check', 14)}${esc(x)}</span>`).join('')}</div></div>
    <div class="offer">${icon(t.plans.offerIcon || 'badge-percent', 26)}<div><b>${esc(t.plans.offerTitle)}</b><p>${esc(t.plans.offer)}</p></div></div>
    <div class="cta">
      <div><h3>${esc(t.cta.title)}</h3><p>${esc(t.cta.text)}</p></div>
      <div class="cta-btn"><span class="cta-label">${esc(t.cta.button)}${icon(c.lang === 'ar' ? 'arrow-left' : 'arrow-right', 15, 2.6)}</span><small dir="ltr">${esc(brand.site)}</small></div>
    </div>
    <p class="fine pinned">${esc(t.plans.fine)}</p>
    ${footer(c, t, 6)}
  </section>`);
  return P.join('\n');
}

const css = c => {
  const ar = c.lang === 'ar';
  const start = ar ? 'right' : 'left', end = ar ? 'left' : 'right';
  const display = ar ? `'Plex Arabic', 'Inter Display', 'Inter', sans-serif` : `'Inter Display', 'Inter', sans-serif`;
  return `
${[400, 500, 600, 700].map(w => `@font-face{font-family:'Plex Arabic';font-weight:${w};src:url('${fontUrl(w)}') format('woff2');unicode-range:U+0600-06FF,U+0750-077F,U+08A0-08FF,U+FB50-FDFF,U+FE70-FEFF,U+200C-200F;}`).join('\n')}
@page { size: A4; margin: 0; }
:root { --navy:#0b1f3a; --ink:#17233a; --muted:#5d6b80; --line:#e3e9f1; --bg:#f4f7fb; --accent:${c.accent}; --accent2:${c.accent2}; --soft:${c.soft}; }
* { box-sizing: border-box; }
html, body { margin:0; padding:0; }
body { font-family: ${ar ? `'Plex Arabic', 'Inter', sans-serif` : `'Inter', sans-serif`}; color: var(--ink); font-size: ${ar ? '10pt' : '9.8pt'}; line-height: ${ar ? 1.6 : 1.45}; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
.num { font-family: 'Inter', sans-serif; font-variant-numeric: tabular-nums; direction: ltr; unicode-bidi: isolate; }
.page { width:210mm; height:297mm; position:relative; overflow:hidden; break-after: page; background:#fff; }
.pad { padding: 14mm 15mm 0; display:flex; flex-direction:column; }
.pad > .eyebrow { align-self: flex-start; }
h1, h2, h3, h4 { margin:0; color: var(--navy); }
h1, h2, .cover .lead, .intro { text-wrap: balance; }
h2 { font-family: ${display}; font-size: ${ar ? '23pt' : '24pt'}; line-height: ${ar ? 1.35 : 1.12}; font-weight: 700; margin: 2.5mm 0 2.5mm; letter-spacing: ${ar ? '0' : '-0.02em'}; }
.intro { color: var(--muted); font-size: 10.6pt; margin: 0 0 5.5mm; max-width: 170mm; }
.eyebrow { display:inline-block; font-size: 8.2pt; font-weight: 700; letter-spacing: ${ar ? '0' : '.12em'}; text-transform: uppercase; color: #fff; background: rgba(255,255,255,.16); padding: 1.1mm 3.2mm; border-radius: 99px; }
.eyebrow.dark { color: var(--accent); background: var(--soft); }
.muted { color: var(--muted); }
svg { flex-shrink: 0; }
.logo { display:flex; align-items:center; gap: 2.5mm; font-weight: 800; font-size: 16pt; color: var(--navy); letter-spacing: ${ar ? '0' : '-.01em'}; }
.logo.light { color:#fff; }
.footer .logo { font-size: 9.5pt; gap: 1.6mm; }
.footer .logo svg { width: 17px; height: 17px; }
/* cover */
.cover { background: radial-gradient(130% 95% at ${ar ? '8%' : '92%'} 0%, var(--accent) 0%, var(--accent2) 42%, var(--navy) 100%); color:#fff; padding: 14mm 15mm; }
.cover .deco { position:absolute; border-radius:50%; border: 1.5px solid rgba(255,255,255,.13); }
.cover .d1 { width:150mm; height:150mm; ${end}:-55mm; top:-40mm; }
.cover .d2 { width:95mm; height:95mm; ${end}:-25mm; top:-12mm; }
.cover .d3 { width:230mm; height:230mm; ${start}:-125mm; bottom:-120mm; background: rgba(255,255,255,.035); }
.cover-top { display:flex; justify-content:space-between; align-items:center; position:relative; z-index:2; }
.badge { display:inline-flex; align-items:center; gap:1.6mm; background: rgba(255,255,255,.95); color: var(--navy); font-weight:700; font-size: 8.4pt; padding: 1.8mm 3.4mm; border-radius: 99px; }
.badge svg { color: var(--accent); }
.cover-text { position:relative; z-index:2; margin-top: 27mm; max-width: 158mm; }
.cover h1 { color:#fff; font-family: ${display}; font-size: ${ar ? '34pt' : '40pt'}; line-height: ${ar ? 1.3 : 1.04}; font-weight: 800; letter-spacing: ${ar ? '0' : '-0.03em'}; margin: 5mm 0 4.5mm; }
.cover .lead { font-size: 12.4pt; line-height: 1.5; color: rgba(255,255,255,.9); margin:0; max-width: 140mm; }
.cover-art { position:absolute; z-index:2; bottom: 27mm; left: 0; right: 0; display:flex; justify-content:center; align-items:flex-end; gap: 7mm; }
.cover-art .tablet { zoom: 1.04; }
.cover-art .phone { zoom: 1.02; }
.cover-art.n2 .phone + .phone { margin-bottom: 9mm; }
.cover-art .phone:first-child { transform: rotate(${ar ? '3deg' : '-3deg'}); }
.cover-art .phone:last-child { transform: rotate(${ar ? '-3deg' : '3deg'}); }
.cover-art .tablet + .phone { margin-${start}: -12mm; margin-bottom: -10mm; transform: rotate(${ar ? '-3deg' : '3deg'}); }
.cover-strip { position:absolute; z-index:3; left:0; right:0; bottom:0; display:flex; justify-content:space-around; gap: 4mm; background: rgba(5,14,30,.62); padding: 5.5mm 10mm; }
.cover-strip div { display:flex; align-items:center; gap: 2mm; font-weight:600; font-size: 9.6pt; color:#fff; }
.cover-strip svg { color: var(--accent); background:#fff; border-radius: 50%; padding: 3px; width: 22px; height: 22px; }
/* why */
.why { display:grid; grid-template-columns: 1fr 1fr; gap: 5mm; }
.why .col { border-radius: 5mm; padding: 5.5mm 5.5mm 2mm; }
.why .pains { background: #fdf2f1; }
.why .gains { background: var(--soft); }
.why h3 { display:flex; align-items:center; gap:2mm; font-size: 12.5pt; margin-bottom: 3.5mm; }
.why .pains h3 svg, .why .pains .li svg { color:#d2453a; }
.why .gains h3 svg, .why .gains .li svg { color: var(--accent); }
.why .li { display:flex; gap: 2.5mm; align-items:flex-start; margin: 0 0 3.4mm; font-size: 9.8pt; }
.why .li svg { margin-top: ${ar ? '3.5px' : '2px'}; }
.statement { margin: 6mm 0 7mm; display:flex; gap: 5mm; align-items:center; background: var(--navy); color:#fff; border-radius: 5mm; padding: 6.5mm 8mm; }
.statement p { margin:0; font-size: 12.6pt; font-weight: 600; line-height: 1.5; }
.st-icon { width: 14mm; height: 14mm; border-radius: 4mm; background: var(--accent); display:grid; place-items:center; flex-shrink:0; }
.who { display:grid; grid-template-columns: repeat(3, 1fr); gap: 4mm; }
.wcard { border: 1px solid var(--line); border-radius: 4mm; padding: 4.5mm; }
.wcard h4 { font-size: 10.6pt; margin: 2.5mm 0 1mm; }
.wcard p { margin:0; font-size: 8.9pt; color: var(--muted); }
/* features */
.fgrid { display:grid; grid-template-columns: 1fr 1fr; gap: 3.2mm 4.5mm; }
.fcard { display:flex; gap: 3.2mm; padding: 3.6mm 3.8mm; border: 1px solid var(--line); border-radius: 4mm; background:#fff; }
.ficon { width: 10mm; height: 10mm; border-radius: 3mm; background: var(--soft); color: var(--accent); display:grid; place-items:center; flex-shrink: 0; }
.fcard h4, .tcard h4 { font-size: 10.2pt; margin: 0 0 .8mm; line-height: 1.3; }
.fcard p, .tcard p { margin:0; font-size: 8.7pt; color: var(--muted); line-height: ${ar ? 1.55 : 1.42}; }
.tag { display:inline-block; font-size: 6.6pt; font-weight: 700; padding: .3mm 1.8mm; border-radius: 99px; vertical-align: 1px; letter-spacing: ${ar ? '0' : '.04em'}; white-space: nowrap; }
.tag.soon { background:#fff1d6; color:#9a5b00; }
.tag.later { background:#e9ecf4; color:#4b5870; }
.later { margin-top: 3.4mm; display:flex; gap: 2.5mm; align-items:center; flex-wrap: wrap; }
.later b { display:flex; align-items:center; gap: 1.4mm; font-size: 8.8pt; color: var(--navy); }
.chips { display:flex; flex-wrap:wrap; gap: 1.6mm; }
.chip { font-size: 8.1pt; padding: .9mm 2.8mm; background: var(--bg); border: 1px solid var(--line); border-radius: 99px; color: var(--ink); }
.chip.soon { background:#fff8eb; border-color:#f6dba6; }
.chips.big { gap: 2mm; }
.chips.big .chip { font-size: 9.2pt; padding: 1.6mm 3.8mm; }
.showcase { position:relative; flex: 1 1 auto; margin: 5mm 0 15mm; min-height: 60mm; border-radius: 6mm; background: linear-gradient(135deg, var(--soft), #eef3f9); display:flex; align-items:center; justify-content:center; gap: 9mm; overflow:hidden; padding-bottom: 4mm; }
.showcase .caption { position:absolute; bottom: 2.4mm; ${start}: 6mm; margin:0; font-size: 7.8pt; color: var(--muted); }
/* how / trust */
.steps { display:grid; grid-template-columns: repeat(4, 1fr); gap: 4mm; margin: 5mm 0 7mm; }
.step { background: var(--bg); border-radius: 4mm; padding: 5mm 4mm; }
.num-badge { width: 9mm; height: 9mm; border-radius: 50%; background: var(--accent); color:#fff; display:grid; place-items:center; font-weight: 800; font-size: 11pt; margin-bottom: 3mm; }
.step h4 { font-size: 10.4pt; margin-bottom: 1.4mm; }
.step p { margin:0; font-size: 8.8pt; color: var(--muted); }
.band { background: var(--navy); color:#fff; border-radius: 5mm; padding: 6mm 7mm; margin-bottom: 8mm; }
.band h3 { color:#fff; display:flex; align-items:center; gap: 2mm; font-size: 12.2pt; margin-bottom: 4mm; }
.band h3 svg { color: var(--accent); }
.band .chip { background: rgba(255,255,255,.1); border-color: rgba(255,255,255,.22); color:#fff; }
.sub-h { font-size: 13.5pt; margin: 0 0 4mm; }
.tgrid { display:grid; grid-template-columns: 1fr 1fr; gap: 5mm 6mm; }
.tcard { display:flex; gap: 3.2mm; }
.notice { display:flex; gap: 3mm; align-items:flex-start; margin-top: 7mm; background:#fff8eb; border-radius: 4mm; padding: 4mm 5mm; }
.notice svg { color:#d98a00; margin-top: 2px; }
.notice p { margin:0; font-size: 8.8pt; }
.faq-h { margin-top: 8mm; }
.faq { display:grid; grid-template-columns: 1fr 1fr; gap: 4.5mm 6mm; }
.qa h4 { display:flex; gap: 2mm; align-items:flex-start; font-size: 10pt; margin-bottom: 1.2mm; line-height: 1.35; }
.qa h4 svg { color: var(--accent); margin-top: ${ar ? '3px' : '1px'}; }
.qa p { margin: 0; font-size: 8.8pt; color: var(--muted); padding-${start}: 6.2mm; }
.fine { font-size: 7.5pt; color: var(--muted); margin: 3.5mm 0 0; line-height: 1.5; }
/* plans */
.plans { display:grid; gap: 3.6mm; margin: 7mm 0 6mm; }
.plans.n2 { grid-template-columns: 1fr 1fr; }
.plans.n3 { grid-template-columns: repeat(3, 1fr); }
.plans.n4 { grid-template-columns: repeat(4, 1fr); }
.plan { border: 1px solid var(--line); border-radius: 5mm; padding: 5mm 4mm 3.5mm; position:relative; background:#fff; }
.plan.hl { border: 2px solid var(--accent); box-shadow: 0 6px 18px rgba(11,31,58,.10); }
.ribbon { position:absolute; top: -3mm; ${end}: 4mm; background: var(--accent); color:#fff; font-size: 7.3pt; font-weight:700; padding: .8mm 3mm; border-radius: 99px; white-space: nowrap; }
.plan h4 { font-size: 11.2pt; }
.price { margin: 2mm 0 1mm; display:flex; align-items:baseline; gap: 1.5mm; flex-wrap: wrap; }
.price b { font-size: 20pt; color: var(--navy); font-weight: 800; letter-spacing: ${ar ? '0' : '-.02em'}; line-height: 1.2; }
.price span { font-size: 8.2pt; color: var(--muted); }
.limit { font-size: 8.4pt; font-weight: 600; color: var(--accent); margin-bottom: 3mm; min-height: 2.6em; }
.plan ul { list-style:none; margin:0; padding:0; }
.plan li { display:flex; gap: 1.6mm; align-items:flex-start; font-size: 8.3pt; margin-bottom: 1.5mm; line-height: 1.4; }
.plan li svg { color: var(--accent); margin-top: ${ar ? '3px' : '2px'}; }
.included { background: var(--bg); border-radius: 4mm; padding: 4.5mm 6mm; margin-bottom: 5mm; }
.included b { display:block; font-size: 10.4pt; color: var(--navy); margin-bottom: 2.6mm; }
.inc-grid { display:grid; grid-template-columns: repeat(3, 1fr); gap: 2.2mm 4mm; }
.inc-grid span { display:flex; align-items:center; gap: 1.6mm; font-size: 8.8pt; }
.inc-grid svg { color: var(--accent); }
.fine.pinned { position:absolute; left: 15mm; right: 15mm; bottom: 14mm; margin: 0; }
.offer { display:flex; gap: 4mm; align-items:center; background: #fff8eb; border: 1px dashed #f0b44c; border-radius: 4mm; padding: 4mm 6mm; margin-bottom: 5mm; }
.offer > svg { color:#d98a00; }
.offer b { font-size: 10.8pt; color: var(--navy); }
.offer p { margin: .5mm 0 0; font-size: 9pt; color: var(--ink); }
.cta { display:flex; justify-content:space-between; align-items:center; gap: 6mm; background: linear-gradient(${ar ? '240deg' : '120deg'}, var(--navy), var(--accent2)); color:#fff; border-radius: 5mm; padding: 7mm 8mm; }
.cta h3 { color:#fff; font-size: 14.5pt; margin-bottom: 1.5mm; }
.cta p { margin:0; color: rgba(255,255,255,.88); font-size: 9.4pt; max-width: 110mm; }
.cta-btn { display:flex; flex-direction:column; align-items:center; gap: 1mm; background:#fff; color: var(--navy); border-radius: 4mm; padding: 4mm 6mm; font-weight: 800; font-size: 11pt; text-align:center; flex-shrink:0; }
.cta-label { display:flex; align-items:center; gap: 1.8mm; }
.cta-btn svg { color: var(--accent); }
.cta-btn small { font-weight: 600; color: var(--muted); font-size: 8.2pt; }
/* footer */
.footer { position:absolute; left: 15mm; right: 15mm; bottom: 5mm; display:flex; justify-content:space-between; align-items:center; font-size: 7.6pt; border-top: 1px solid var(--line); padding-top: 2.3mm; }
.f-brand { display:flex; align-items:center; gap: 3mm; }
/* devices */
.phone { width: 58mm; height: 118mm; border-radius: 8.5mm; background: #0d1626; padding: 2.2mm; position:relative; box-shadow: 0 14px 34px rgba(5,14,30,.35); flex-shrink:0; }
.phone .notch { position:absolute; top: 3.6mm; left: 50%; transform: translateX(-50%); width: 15mm; height: 3.3mm; border-radius: 99px; background:#0d1626; z-index: 3; }
.phone .screen, .tablet .screen { background: #f7f9fc; border-radius: 6.4mm; width:100%; height:100%; overflow:hidden; color: var(--ink); font-size: 7.3pt; line-height: 1.38; padding: 8.5mm 3.2mm 3mm; position:relative; }
.tablet { width: 124mm; height: 82mm; border-radius: 6mm; background:#0d1626; padding: 2.6mm; box-shadow: 0 14px 34px rgba(5,14,30,.30); flex-shrink:0; }
.tablet .screen { border-radius: 4mm; padding: 3.6mm 4mm; }
.statusbar { position:absolute; top: 3.2mm; left: 5.5mm; right: 5.5mm; display:flex; justify-content:space-between; font-size: 6.2pt; font-weight: 700; color: var(--ink); font-family: 'Inter', sans-serif; direction: ltr; letter-spacing: -.02em; }
.app-top { display:flex; justify-content:space-between; align-items:center; margin-bottom: 2.4mm; gap: 2mm; }
.app-top b { font-size: 9.4pt; color: var(--navy); }
.app-top > svg { color: var(--accent); }
.pill { display:inline-flex; align-items:center; gap: 1mm; font-size: 6.3pt; font-weight:700; padding: .5mm 2.2mm; border-radius: 99px; background: var(--accent); color:#fff; white-space: nowrap; }
.pill.soft { background: var(--soft); color: var(--accent); }
.kpis { display:grid; grid-template-columns: 1fr 1fr; gap: 1.8mm; margin-bottom: 2mm; }
.kpi { background:#fff; border: 1px solid var(--line); border-radius: 2.6mm; padding: 1.7mm 2.2mm; display:flex; flex-direction:column; }
.kpi span { font-size: 6.2pt; color: var(--muted); }
.kpi b { font-size: 10.6pt; color: var(--navy); }
.kpi small { font-size: 5.8pt; color: var(--muted); }
.kpi.big { margin-bottom: 1mm; }
.kpi.big b { font-size: 19pt; color: var(--accent); line-height: 1.1; }
.card-mini { background:#fff; border: 1px solid var(--line); border-radius: 2.6mm; padding: 2mm; margin-bottom: 2mm; }
.chart { width: 100%; height: auto; display:block; }
.list-title { font-size: 6.6pt; font-weight: 700; color: var(--muted); margin: 1.6mm 0 1mm; }
.row { display:flex; justify-content:space-between; align-items:center; background:#fff; border: 1px solid var(--line); border-radius: 2mm; padding: 1.2mm 2mm; margin-bottom: 1.1mm; }
.tag-mini { font-size: 6pt; font-weight: 700; color: #9a5b00; background:#fff1d6; border-radius: 99px; padding: .2mm 1.6mm; }
.desk { display:grid; grid-template-columns: 1.45fr 1fr; gap: 3mm; }
.desk-main { display:flex; flex-direction:column; gap: 2.2mm; }
.scan { display:flex; align-items:center; gap: 2mm; border: 1.5px dashed var(--accent); color: var(--accent); border-radius: 3mm; padding: 4.5mm 3mm; font-weight: 700; font-size: 8pt; background: var(--soft); }
.member { display:flex; align-items:center; gap: 2.4mm; background:#fff; border: 1px solid var(--line); border-radius: 3mm; padding: 2.6mm; }
.avatar { width: 11mm; height: 11mm; border-radius: 50%; background: linear-gradient(135deg, var(--accent), var(--accent2)); color:#fff; display:grid; place-items:center; font-weight: 800; font-size: 8.6pt; flex-shrink:0; }
.avatar.sm { width: 7.5mm; height: 7.5mm; font-size: 6.4pt; }
.avatar.xs { width: 5mm; height: 5mm; font-size: 5.6pt; display:inline-grid; }
.queue { display:grid; grid-template-columns: 1fr 1fr 1fr; gap: 1.6mm; }
.queue .row { flex-direction: column; align-items: flex-start; gap: .6mm; margin: 0; }
.queue .qname { display:flex; align-items:center; gap: 1.4mm; font-weight: 700; color: var(--navy); }
.queue b { font-size: 8pt; color: var(--accent); }
.mem-info { display:flex; flex-direction:column; flex:1; }
.mem-info b { font-size: 8.8pt; color: var(--navy); }
.ok { display:flex; align-items:center; gap: 1mm; background: #e3f6ea; color: #18864b; font-weight: 800; border-radius: 99px; padding: 1mm 2.6mm; font-size: 7.4pt; }
.note { display:flex; align-items:center; gap: 1.5mm; font-size: 6.9pt; color: #9a5b00; background:#fff8eb; border-radius: 2mm; padding: 1.6mm 2.2mm; }
.desk-side { display:flex; flex-direction:column; }
.day-title { font-size: 7.8pt; font-weight: 700; color: var(--accent); margin-bottom: 2mm; }
.ex { display:flex; gap: 2mm; align-items:center; background:#fff; border: 1px solid var(--line); border-radius: 2.6mm; padding: 2mm; margin-bottom: 1.6mm; }
.ex.active { border-color: var(--accent); box-shadow: 0 0 0 2px var(--soft); }
.ex-icon { width: 7mm; height: 7mm; border-radius: 2mm; background: var(--soft); color: var(--accent); display:grid; place-items:center; flex-shrink:0; }
.ex-body { display:flex; flex-direction:column; }
.ex-body b { font-size: 7.5pt; color: var(--navy); }
.ex-body .num { text-align: ${start}; }
.ex-body small { font-size: 6.1pt; color: var(--muted); }
.rest { display:flex; align-items:center; gap: 1.4mm; justify-content:center; font-weight:700; color: var(--accent); margin: 2.2mm 0; font-size: 7.2pt; }
.btn { background: var(--accent); color:#fff; text-align:center; font-weight: 800; border-radius: 2.6mm; padding: 2.2mm; font-size: 7.8pt; display:flex; align-items:center; justify-content:center; gap: 1.2mm; }
.btn.sm { padding: 1.5mm 2.6mm; font-size: 6.9pt; }
.btn.ghost { background: #fff; color: var(--accent); border: 1px solid var(--accent); }
.review { display:grid; grid-template-columns: 1.1fr 1fr; gap: 3.2mm; }
.photos { display:grid; grid-template-columns: 1fr 1fr; gap: 2mm; }
.ph { display:flex; flex-direction:column; align-items:center; gap: 1mm; font-size: 6.5pt; color: var(--muted); }
.sil { width: 100%; height: auto; border-radius: 2mm; }
.rev-side { display:flex; flex-direction:column; gap: 1.2mm; }
.client { display:flex; align-items:center; gap: 1.6mm; font-size: 8pt; }
.metric { display:flex; justify-content:space-between; align-items:baseline; font-size: 6.7pt; color: var(--muted); }
.metric b { font-size: 9pt; color: var(--navy); }
.sets { background:#fff; border: 1px solid var(--line); border-radius: 2.6mm; overflow:hidden; margin-bottom: 2mm; }
.set-row { display:grid; grid-template-columns: 1fr 1.3fr 1.3fr .8fr; padding: 1.5mm 2.4mm; border-bottom: 1px solid var(--line); text-align:center; font-weight: 600; align-items:center; }
.set-row:last-child { border-bottom: 0; }
.set-row.head { background: var(--bg); font-size: 6.2pt; color: var(--muted); }
.set-row.done { color: var(--accent); }
.set-row svg { display:block; margin: 0 auto; }
.timer-ring { position:relative; width: 25mm; height: 25mm; margin: 2mm auto 0; }
.timer-ring svg { width:100%; height:100%; }
.timer-ring div { position:absolute; inset:0; display:flex; flex-direction:column; align-items:center; justify-content:center; }
.timer-ring b { font-size: 10pt; color: var(--navy); }
.timer-ring span { font-size: 6pt; color: var(--muted); }
.pr { display:flex; align-items:center; gap: 1.4mm; background: #fff1d6; color: #8a5200; font-weight: 700; border-radius: 2.4mm; padding: 1.8mm 2.2mm; margin-bottom: 2mm; font-size: 6.9pt; }
.memcard { display:flex; justify-content:space-between; align-items:center; gap: 2mm; background: linear-gradient(135deg, var(--navy), var(--accent2)); color:#fff; border-radius: 3mm; padding: 2.6mm 3mm; }
.mc-text { display:flex; flex-direction:column; }
.memcard small { font-size: 5.9pt; opacity: .8; }
.memcard b { font-size: 8.4pt; }
.memcard span { font-size: 6.2pt; opacity: .88; }
.qr-sm { width: 12mm; height: 12mm; border-radius: 1.5mm; overflow:hidden; flex-shrink:0; }
.qr-sm svg, .memcard.big svg { width: 100%; height: 100%; display:block; }
.memcard.big { flex-direction: column; align-items: stretch; padding: 3.4mm; gap: 2.4mm; margin-bottom: 1mm; }
.memcard.big svg { width: 30mm; height: 30mm; margin: 0 auto; border-radius: 2mm; }
.memcard.big .qr-hint { text-align:center; font-size: 6.2pt; }
.tabs { display:flex; gap: 1.5mm; margin-bottom: 2.2mm; }
.tabs span { font-size: 6.6pt; padding: .8mm 2.6mm; border-radius: 99px; background:#fff; border: 1px solid var(--line); color: var(--muted); font-weight: 600; }
.tabs span.on { background: var(--accent); border-color: var(--accent); color:#fff; }
table.bt { width:100%; border-collapse: separate; border-spacing: 0; background:#fff; border: 1px solid var(--line); border-radius: 2.6mm; overflow:hidden; font-size: 7.2pt; }
table.bt th { background: var(--bg); color: var(--muted); font-size: 6.4pt; font-weight: 700; text-align: ${start}; padding: 1.4mm 2.4mm; }
table.bt td { padding: 1.5mm 2.4mm; border-top: 1px solid var(--line); }
table.bt tr:first-child td { border-top: 0; }
table.bt.grid td:first-child { display:flex; align-items:center; gap: 1.2mm; font-weight: 600; color: var(--navy); }
table.bt.grid td:first-child svg { color: #b3bfcf; }
table.bt td.end { text-align: ${end}; }
table.bt tr.total td { font-weight: 800; color: var(--navy); background: var(--soft); }
.bt-actions { display:flex; justify-content:flex-end; gap: 2mm; margin-top: 2.4mm; }
.st { display:flex; flex-direction:column; gap: 2.4mm; }
.st-head { display:flex; align-items:center; gap: 2mm; }
.st-head div:last-child { display:flex; flex-direction:column; }
.st-head b { font-size: 8.6pt; color: var(--navy); }
.st-head small { font-size: 6.4pt; color: var(--muted); }
.st-foot { display:flex; justify-content:space-between; align-items:center; }
.chat-top { display:flex; align-items:center; gap: 2mm; padding-bottom: 2mm; border-bottom: 1px solid var(--line); margin-bottom: 2mm; }
.chat-top div:last-child { display:flex; flex-direction:column; }
.chat-top b { font-size: 8.4pt; color: var(--navy); }
.chat-top small { font-size: 6pt; color: #18864b; }
.bubbles { display:flex; flex-direction:column; gap: 1.8mm; }
.bub { max-width: 80%; padding: 1.8mm 2.4mm; border-radius: 3mm; font-size: 6.9pt; line-height: 1.4; }
.bub.them { align-self: flex-start; background:#fff; border: 1px solid var(--line); border-start-start-radius: .8mm; }
.bub.me { align-self: flex-end; background: var(--accent); color:#fff; border-start-end-radius: .8mm; }
.bub.voice { display:flex; align-items:center; gap: 1.6mm; }
.bub.voice .wave { width: 18mm; height: 4mm; }
.bub.me .wave { fill:#fff; } .bub.them .wave { fill: var(--accent); }
.bub.img { padding: 1.2mm; display:flex; flex-direction:column; gap: 1mm; }
.bub.img span { padding: 0 1.2mm .6mm; }
.food { width: 30mm; height: 17mm; border-radius: 2mm; background: linear-gradient(135deg, #f7d9a8, #d8efc4); display:grid; place-items:center; color: #4f7a2c; }
.composer { position:absolute; left: 3.2mm; right: 3.2mm; bottom: 3mm; display:flex; justify-content:space-between; align-items:center; background:#fff; border: 1px solid var(--line); border-radius: 99px; padding: 1.8mm 3mm; color: var(--muted); font-size: 6.8pt; }
.composer svg { color: var(--accent); }
`;
};

async function render(browser, audience, lang) {
  const b = brochures[audience];
  const t = b[lang];
  const c = { lang, accent: b.accent, accent2: b.accent2, soft: b.soft };
  const m = { ...mockShared[lang], ...(t.mock || {}) };
  const art = names => names.map(n => mockups[n](m)).join('');
  t.coverArt = art(b.cover); t.coverCount = b.cover.length;
  t.f1.art = art(b.f1Art); t.f1.artCount = b.f1Art.length;
  t.f2.art = art(b.f2Art); t.f2.artCount = b.f2Art.length;
  const html = `<!doctype html><html lang="${lang}" dir="${lang === 'ar' ? 'rtl' : 'ltr'}"><head><meta charset="utf-8"><title>${esc(brand[lang])} — ${esc(t.tag)}</title><style>${css(c)}</style></head><body>${pages(c, t)}</body></html>`;
  const base = `Tamreen-${b.file}-${lang.toUpperCase()}`;
  const htmlPath = path.join(htmlDir, base + '.html');
  fs.writeFileSync(htmlPath, html);
  const page = await browser.newPage();
  await page.goto(pathToFileURL(htmlPath).href, { waitUntil: 'load' });
  await page.evaluate(() => document.fonts.ready);
  // Scale each showcase's devices to the room left on its page.
  await page.evaluate(() => {
    const mm = 96 / 25.4;
    document.querySelectorAll('.showcase').forEach(sc => {
      const room = sc.clientHeight - 14 * mm;
      sc.querySelectorAll('.phone').forEach(d => { d.style.zoom = Math.min(0.72, room / (118 * mm)).toFixed(3); });
      sc.querySelectorAll('.tablet').forEach(d => { d.style.zoom = Math.min(0.92, room / (82 * mm)).toFixed(3); });
    });
  });
  // Layout check: content must stay above the footer / showcase, cover text above the devices.
  const problems = await page.evaluate(() => {
    const mm = 96 / 25.4;
    return [...document.querySelectorAll('.page')].map((p, i) => {
      const pr = p.getBoundingClientRect();
      const out = [];
      const limit = pr.bottom - 13 * mm;
      p.querySelectorAll('.why, .statement, .who, .fgrid, .later, .steps, .band, .tgrid, .faq, .notice, .plans, .included, .offer, .cta').forEach(e => {
        if (e.getBoundingClientRect().bottom > limit) out.push(`${e.className} past footer by ${((e.getBoundingClientRect().bottom - limit) / mm).toFixed(1)}mm`);
      });
      const fine = p.querySelector('.fine.pinned'), cta = p.querySelector('.cta');
      if (fine && cta && fine.getBoundingClientRect().top - cta.getBoundingClientRect().bottom < 4 * mm) out.push('fine print meets CTA');
      const sc = p.querySelector('.showcase');
      if (sc) {
        const top = sc.getBoundingClientRect().top;
        if (sc.clientHeight < 72 * mm) out.push(`showcase only ${(sc.clientHeight / mm).toFixed(0)}mm tall`);
        sc.querySelectorAll('.phone, .tablet').forEach(d => { const r = d.getBoundingClientRect(), s = sc.getBoundingClientRect(); if (r.top < s.top || r.bottom > s.bottom) out.push('device clipped in showcase'); });
      }
      const ct = p.querySelector('.cover-text'), ca = p.querySelector('.cover-art');
      if (ct && ca) { const g = ca.getBoundingClientRect().top - ct.getBoundingClientRect().bottom; if (g < 4 * mm) out.push(`cover text meets devices (${(g / mm).toFixed(1)}mm)`); }
      return out.length ? `p${i + 1}: ${out.join('; ')}` : null;
    }).filter(Boolean);
  });
  await page.pdf({ path: path.join(outDir, base + '.pdf'), format: 'A4', printBackground: true, preferCSSPageSize: true });
  await page.close();
  console.log(base.padEnd(26), problems.length ? 'LAYOUT ' + problems.join(' | ') : 'ok');
}

const cfg = JSON.parse(fs.readFileSync(path.join(here, 'pconf.json'), 'utf8'));
const browser = await puppeteer.launch({ executablePath: cfg.executablePath, args: cfg.args });
for (const a of Object.keys(brochures)) {
  if (onlyAudience && a !== onlyAudience) continue;
  for (const l of ['en', 'ar']) {
    if (onlyLang && l !== onlyLang) continue;
    await render(browser, a, l);
  }
}
await browser.close();
fs.rmSync(htmlDir, { recursive: true, force: true });
