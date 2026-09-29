// Prints the admin site's Content-Security-Policy header as a Caddy snippet, allowing exactly the
// inline scripts (and import map) of this build (by hash) and nothing else inline. Run after `nuxi generate`.
import { createHash } from 'node:crypto'
import { readFileSync } from 'node:fs'

const html = readFileSync(new URL('../.output/public/index.html', import.meta.url), 'utf8')
const hashes = [...html.matchAll(/<script(?: type="importmap")?>([\s\S]*?)<\/script>/g)]
  .map(([, body]) => `'sha256-${createHash('sha256').update(body).digest('base64')}'`)

const policy = [
  "default-src 'self'",
  `script-src 'self' ${hashes.join(' ')}`.trim(),
  "style-src 'self' 'unsafe-inline' https://fonts.googleapis.com",
  "font-src 'self' https://fonts.gstatic.com",
  "img-src 'self' blob: data:",
  "frame-src 'self' blob:",
  "connect-src 'self'",
  "frame-ancestors 'none'",
  "base-uri 'none'",
  "form-action 'self'",
].join('; ')

process.stdout.write(`header Content-Security-Policy "${policy}"\n`)
