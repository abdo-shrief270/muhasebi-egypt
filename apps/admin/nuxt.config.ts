// The platform admin panel: its own app, built and served on its own domain (ADMIN_DOMAIN),
// so none of it ships to shop users. Talks only to /api/v1/admin/*.
export default defineNuxtConfig({
  compatibilityDate: '2026-09-01',
  devtools: { enabled: false },
  modules: ['@nuxt/ui'],
  css: ['~/assets/css/main.css'],
  ssr: false,

  app: {
    head: {
      htmlAttrs: { lang: 'ar', dir: 'rtl' },
      title: 'محاسبي — الإدارة',
      meta: [{ name: 'robots', content: 'noindex, nofollow' }],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap' },
      ],
    },
  },

  ui: { fonts: false },
  colorMode: { preference: 'light', fallback: 'light', storageKey: 'muhasebi-admin-color-mode' },

  icon: {
    clientBundle: {
      scan: true,
      icons: ['lucide:layout-dashboard', 'lucide:banknote', 'lucide:store', 'lucide:sun', 'lucide:moon', 'lucide:log-out', 'lucide:shield', 'lucide:history', 'lucide:message-square-heart', 'lucide:bug', 'lucide:ticket-percent', 'lucide:server'],
    },
  },

  runtimeConfig: {
    public: {
      apiBase: 'http://localhost:8000/api/v1', // NUXT_PUBLIC_API_BASE
    },
  },

  typescript: { strict: true },
})
