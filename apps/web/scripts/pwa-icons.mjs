/**
 * Draws the app icons (installable app, favicon, iOS home screen, manifest shortcuts) from one SVG
 * logo and renders the PNGs with a headless Chromium. The output is committed under public/, so this
 * only runs again when the logo changes (then bump `?v=` in public/site.webmanifest and nuxt.config.ts).
 *
 *   PLAYWRIGHT=/path/to/playwright/index.mjs CHROMIUM=/path/to/chrome node scripts/pwa-icons.mjs
 */
import { mkdir, readFile, writeFile } from 'node:fs/promises'
import { createRequire } from 'node:module'
import { dirname, join } from 'node:path'
import { fileURLToPath } from 'node:url'

const root = join(dirname(fileURLToPath(import.meta.url)), '..')
const pub = join(root, 'public')
const { chromium } = await import(process.env.PLAYWRIGHT ?? 'playwright')

// Brand teal (main.css: primary-600 #0D9488, --app-primary-strong #0F766E).
const defs = `<defs><linearGradient id="g" x1="0" y1="0" x2="1" y2="1"><stop offset="0" stop-color="#14B8A6"/><stop offset="1" stop-color="#0F766E"/></linearGradient></defs>`

/** The sidebar's phone glyph with ledger lines on its screen (right-aligned, like Arabic text); `scale` shrinks it around the centre. */
function glyph(scale = 1) {
  return `<g transform="translate(256 256) scale(${scale}) translate(-256 -256)" fill="none" stroke="#fff" stroke-linecap="round">
    <rect x="168" y="84" width="176" height="344" rx="40" stroke-width="32"/>
    <path d="M226 170h60M226 216h60M252 262h34" stroke-width="24" opacity=".9"/>
    <path d="M236 376h40" stroke-width="28"/>
  </g>`
}

const svg = {
  // Rounded tile, transparent corners: favicon, desktop / Android "any" icon.
  any: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">${defs}<rect width="512" height="512" rx="112" fill="url(#g)"/>${glyph()}</svg>`,
  // Full bleed, glyph inside the 80% safe circle: Android adaptive icons crop it to any shape.
  maskable: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">${defs}<rect width="512" height="512" fill="url(#g)"/>${glyph(0.78)}</svg>`,
  // iOS rounds the corners itself and shows transparency as black: full bleed.
  apple: `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 512 512">${defs}<rect width="512" height="512" fill="url(#g)"/>${glyph(0.86)}</svg>`,
}

const require = createRequire(import.meta.url)
const lucide = JSON.parse(await readFile(require.resolve('@iconify-json/lucide/icons.json'), 'utf8'))
function shortcut(name) {
  const body = lucide.icons[name].body.replaceAll('currentColor', '#fff').replace(/stroke-width="2"/g, 'stroke-width="1.75"')
  return `<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 96 96">${defs}<rect width="96" height="96" rx="22" fill="url(#g)"/><svg x="24" y="24" width="48" height="48" viewBox="0 0 24 24">${body}</svg></svg>`
}

const browser = await chromium.launch(process.env.CHROMIUM ? { executablePath: process.env.CHROMIUM } : {})
const page = await browser.newPage()
async function png(markup, size) {
  await page.setViewportSize({ width: size, height: size })
  await page.setContent(`<html><body style="margin:0;background:transparent">${markup.replace('<svg ', `<svg width="${size}" height="${size}" style="display:block" `)}</body></html>`)
  return page.screenshot({ omitBackground: true, type: 'png' })
}

/** A .ico holding PNG images (supported by every browser since IE Vista era). */
function ico(images) {
  const header = Buffer.alloc(6 + 16 * images.length)
  header.writeUInt16LE(0, 0)
  header.writeUInt16LE(1, 2)
  header.writeUInt16LE(images.length, 4)
  let offset = header.length
  images.forEach(({ size, data }, i) => {
    const at = 6 + 16 * i
    header.writeUInt8(size >= 256 ? 0 : size, at)
    header.writeUInt8(size >= 256 ? 0 : size, at + 1)
    header.writeUInt16LE(1, at + 4) // planes
    header.writeUInt16LE(32, at + 6) // bits per pixel
    header.writeUInt32LE(data.length, at + 8)
    header.writeUInt32LE(offset, at + 12)
    offset += data.length
  })
  return Buffer.concat([header, ...images.map(i => i.data)])
}

await mkdir(join(pub, 'icons'), { recursive: true })
await writeFile(join(pub, 'favicon.svg'), svg.any)
await writeFile(join(pub, 'icons', 'icon-192.png'), await png(svg.any, 192))
await writeFile(join(pub, 'icons', 'icon-512.png'), await png(svg.any, 512))
await writeFile(join(pub, 'icons', 'maskable-192.png'), await png(svg.maskable, 192))
await writeFile(join(pub, 'icons', 'maskable-512.png'), await png(svg.maskable, 512))
await writeFile(join(pub, 'apple-touch-icon.png'), await png(svg.apple, 180))
await writeFile(join(pub, 'favicon.ico'), ico(await Promise.all([16, 32, 48].map(async size => ({ size, data: await png(svg.any, size) })))))
for (const [file, icon] of Object.entries({ pos: 'shopping-cart', repair: 'wrench', price: 'scan-barcode', shift: 'wallet' })) {
  await writeFile(join(pub, 'icons', `shortcut-${file}.png`), await png(shortcut(icon), 96))
}

await browser.close()
console.log('icons written to public/')
