// https://nuxt.com/docs/api/configuration/nuxt-config
import { createHash } from 'node:crypto'
import { readdir, readFile, writeFile } from 'node:fs/promises'
import { join, relative, sep } from 'node:path'

async function filesUnder(dir: string): Promise<string[]> {
  const entries = await readdir(dir, { withFileTypes: true }).catch(() => [])
  const nested = await Promise.all(entries.map(e => (e.isDirectory() ? filesUnder(join(dir, e.name)) : [join(dir, e.name)])))
  return nested.flat()
}

export default defineNuxtConfig({
  compatibilityDate: '2026-09-01',
  devtools: { enabled: true },
  modules: ['@nuxt/ui', '@pinia/nuxt'],
  css: ['~/assets/css/main.css'],

  // The dashboard and the POS are a client-rendered app (the POS must work offline later).
  ssr: false,

  app: {
    head: {
      htmlAttrs: { lang: 'ar', dir: 'rtl' },
      title: 'محاسبي',
      // viewport-fit=cover: the installed app draws under the notch / home bar (safe-area insets in main.css).
      viewport: 'width=device-width, initial-scale=1, viewport-fit=cover',
      // Installable app (public/site.webmanifest). The theme-color metas (light / dark, or the theme picked
      // in the app) come from plugins/pwa.client.ts. Bump ?v= when the icons change.
      meta: [
        { name: 'application-name', content: 'محاسبي' },
        { name: 'mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-capable', content: 'yes' },
        { name: 'apple-mobile-web-app-title', content: 'محاسبي' },
        { name: 'apple-mobile-web-app-status-bar-style', content: 'default' },
        { name: 'format-detection', content: 'telephone=no' },
      ],
      script: [
        // Chrome may offer the install prompt before the app's code runs: keep it for useInstallApp().
        { innerHTML: 'addEventListener("beforeinstallprompt",function(e){e.preventDefault();window.__installPrompt=e})', tagPosition: 'head' },
      ],
      link: [
        { rel: 'manifest', href: '/site.webmanifest' },
        { rel: 'icon', href: '/favicon.ico?v=1', sizes: '48x48' },
        { rel: 'icon', href: '/favicon.svg?v=1', type: 'image/svg+xml' },
        { rel: 'apple-touch-icon', href: '/apple-touch-icon.png?v=1' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap' },
      ],
    },
  },

  // Cairo is loaded from <head>; don't resolve fonts at build time.
  ui: { fonts: false },

  // Light unless the user picks dark (or "follow the device") from the top bar.
  colorMode: { preference: 'light', fallback: 'light', storageKey: 'muhasebi-color-mode' },

  // Menu icons come from the API (module manifests), so the bundler can't see them.
  // Keep this list in sync with the icons used in apps/api/app/Modules/*/module.php.
  icon: {
    clientBundle: {
      scan: true,
      icons: [
        'lucide:gauge', 'lucide:chevron-down', 'lucide:shopping-bag', 'lucide:phone-call', 'lucide:unlink', 'lucide:settings', 'lucide:arrow-right', 'lucide:image-plus', 'lucide:external-link', 'lucide:upload', 'lucide:image', 'lucide:fingerprint', 'lucide:key-round', 'lucide:clock-alert', 'lucide:x', 'lucide:trending-down', 'lucide:minus', 'lucide:package-x', 'lucide:lock-open', 'lucide:circle', 'lucide:arrow-left-right', 'lucide:toggle-right', 'lucide:calendar-clock', 'lucide:chart-column', 'lucide:credit-card',
        'lucide:file-check', 'lucide:git-compare-arrows', 'lucide:message-circle', 'lucide:package',
        'lucide:receipt', 'lucide:ship', 'lucide:shopping-cart', 'lucide:smartphone', 'lucide:store',
        'lucide:truck', 'lucide:undo-2', 'lucide:users', 'lucide:wallet', 'lucide:warehouse', 'lucide:wrench',
        'lucide:box', 'lucide:handshake', 'lucide:receipt-text', 'lucide:tag', 'lucide:tags', 'lucide:percent', 'lucide:scan-line',
        // Icons named in .ts files / script blocks, which the scan doesn't see.
        'lucide:package-plus', 'lucide:clipboard-check', 'lucide:file-spreadsheet', 'lucide:triangle-alert', 'lucide:circle-x',
        'lucide:layout-dashboard', 'lucide:notebook-pen', 'lucide:hand-coins', 'lucide:trending-up', 'lucide:scan-barcode', 'lucide:user-plus', 'lucide:check-circle', 'lucide:alarm-clock', 'lucide:banknote', 'lucide:credit-card', 'lucide:smartphone', 'lucide:circle-dot', 'lucide:headphones', 'lucide:boxes', 'lucide:ship', 'lucide:store', 'lucide:hourglass', 'lucide:calendar-clock', 'lucide:lock', 'lucide:ban', 'lucide:shield', 'lucide:sun', 'lucide:moon', 'lucide:log-out', 'lucide:banknote',
        // Notification icons come from the API (Notifications\Listeners).
        'lucide:inbox', 'lucide:circle-check', 'lucide:hammer', 'lucide:package-check', 'lucide:check-check', 'lucide:ban', 'lucide:bell',
        // Customer privacy (customer page consent line / data menu, /privacy sections).
        'lucide:shield-check', 'lucide:shield-alert', 'lucide:shield-question', 'lucide:user-x', 'lucide:download',
        'lucide:database', 'lucide:target', 'lucide:eye', 'lucide:clock', 'lucide:user-check', 'lucide:phone', 'lucide:lock',
        // Security page (navigation, device icons, conditional shield icons).
        'lucide:lock-keyhole', 'lucide:monitor', 'lucide:tablet', 'lucide:shield', 'lucide:shield-check', 'lucide:shield-alert',
        'lucide:wifi-off', 'lucide:cloud-upload', 'lucide:cloud-alert', 'lucide:refresh-cw',
        // «ابدأ من هنا» step icons (from the API) and «ابعت ملاحظة» (menu / Ctrl+K / toast).
        'lucide:boxes', 'lucide:package-plus', 'lucide:file-spreadsheet', 'lucide:shield-check', 'lucide:rocket',
        'lucide:message-square-heart', 'lucide:heart-handshake', 'lucide:bug', 'lucide:lightbulb', 'lucide:circle-help',
        // Services (wallets / airtime): operations, tabs, row menus, the account form.
        'lucide:send', 'lucide:bell-ring', 'lucide:bell-off', 'lucide:smartphone-charging', 'lucide:wallet-cards', 'lucide:list', 'lucide:printer', 'lucide:pencil',
        'lucide:arrow-down-to-line', 'lucide:arrow-up-from-line', 'lucide:check', 'lucide:ellipsis-vertical', 'lucide:rotate-ccw',
        // Supplier returns (tabs, settle options, bin line actions).
        'lucide:layers', 'lucide:file-text', 'lucide:repeat', 'lucide:trash-2', 'lucide:pencil', 'lucide:wallet', 'lucide:banknote',
        // Used devices (tabs, checklist marks, photo picker).
        'lucide:list', 'lucide:circle-check', 'lucide:circle-x', 'lucide:circle-dashed', 'lucide:camera', 'lucide:loader-circle',
        // «نزّل التطبيق» (user menu, Ctrl+K, home card, /settings/app, install steps) and the update toast.
        'lucide:download', 'lucide:monitor-down', 'lucide:monitor-smartphone', 'lucide:laptop', 'lucide:share', 'lucide:square-plus',
        'lucide:book-open', 'lucide:copy', 'lucide:star', 'lucide:printer', 'lucide:mouse-pointer-click', 'lucide:history', 'lucide:zap',
        'lucide:refresh-cw', 'lucide:circle-check', 'lucide:chevron-left', 'lucide:chevron-right', 'lucide:ellipsis-vertical',
      ],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8000/api/v1', // NUXT_PUBLIC_API_BASE
      appVersion: '', // NUXT_PUBLIC_APP_VERSION (e.g. the git sha), sent with feedback and error reports
    },
  },

  typescript: { strict: true },

  hooks: {
    // The service worker (public/sw.js) gets this build's version and its /_nuxt files to precache,
    // so the POS opens offline after one visit and each deploy replaces the old cache.
    async 'nitro:build:public-assets'(nitro) {
      const publicDir = nitro.options.output.publicDir
      const swPath = join(publicDir, 'sw.js')
      const sw = await readFile(swPath, 'utf8').catch(() => null)
      if (sw === null) {
        return
      }
      const assets = (await filesUnder(join(publicDir, '_nuxt')))
        .map(file => '/' + relative(publicDir, file).split(sep).join('/'))
        .filter(url => !url.startsWith('/_nuxt/builds/') && !url.endsWith('.map'))
        .sort()
      const version = createHash('sha256').update(assets.join('\n')).digest('hex').slice(0, 12)
      await writeFile(swPath, sw
        .replace(/^const VERSION = .*$/m, `const VERSION = '${version}'`)
        .replace(/^const PRECACHE = .*$/m, `const PRECACHE = ${JSON.stringify(assets)}`))
    },
  },
})
