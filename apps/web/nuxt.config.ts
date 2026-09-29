// https://nuxt.com/docs/api/configuration/nuxt-config
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
      link: [
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
        'lucide:arrow-left-right', 'lucide:calendar-clock', 'lucide:chart-column', 'lucide:credit-card',
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
      ],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8000/api/v1', // NUXT_PUBLIC_API_BASE
    },
  },

  typescript: { strict: true },
})
