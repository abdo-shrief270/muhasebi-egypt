import tailwindcss from '@tailwindcss/vite'

// «سوق محاسبي» (souq.muhasebi.com): what every listed shop has in stock, nearest first. Server-
// rendered for search engines and link previews, cached a short while; the visitor's place (their
// location or governorate, kept in the browser) re-sorts it there. Design direction B
// («الأقرب ليك»): warm paper, deep teal, a map of the shops around you.
export default defineNuxtConfig({
  compatibilityDate: '2026-09-01',
  devtools: { enabled: false },
  css: ['~/assets/css/main.css', 'leaflet/dist/leaflet.css'],
  vite: { plugins: [tailwindcss()] },

  app: {
    head: {
      htmlAttrs: { lang: 'ar', dir: 'rtl' },
      titleTemplate: '%s · سوق محاسبي',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'format-detection', content: 'telephone=no' },
        { name: 'theme-color', content: '#F7F6F2' },
        { property: 'og:locale', content: 'ar_EG' },
        { property: 'og:site_name', content: 'سوق محاسبي' },
      ],
      link: [
        { rel: 'icon', href: '/favicon.svg', type: 'image/svg+xml' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=IBM+Plex+Sans+Arabic:wght@400;500;600;700&display=swap' },
      ],
    },
  },

  runtimeConfig: {
    // Where this server reaches the API (NUXT_API_INTERNAL; the api container in production).
    apiInternal: 'http://localhost:8000/api/v1',
    public: {
      // This site's own address, for canonical links and the sitemap (NUXT_PUBLIC_MARKET_URL).
      marketUrl: 'https://souq.muhasebi.com',
      // The platform's website: «عندك محل؟ اظهر هنا».
      siteUrl: 'https://muhasebi.com',
    },
  },

  routeRules: {
    // Pages re-render at most every 60 s (stale while refreshing), the same for everyone: the
    // visitor's place (kept in the browser) re-sorts them after load.
    '/**': { cache: { swr: true, maxAge: 60 } },
    '/api/**': { cache: false },
    // The visitor's own: the cart, checkout and an order's page, filled in the browser.
    '/cart': { ssr: false, cache: false },
    '/checkout/**': { ssr: false, cache: false },
    '/o/**': { ssr: false, cache: false },
    '/sitemap.xml': { cache: false },
    '/robots.txt': { cache: false },
  },

  nitro: {
    compressPublicAssets: true,
  },

  typescript: { strict: true },
})
