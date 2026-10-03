import tailwindcss from '@tailwindcss/vite'

// The shops' online stores (store.muhasebi.com/{slug}): server-rendered so search engines and
// link previews see the products and prices, cached a short while (stock moves all day).
// Light on purpose: no UI library, a few inline icons, lazy images — it must be quick on 3G.
export default defineNuxtConfig({
  compatibilityDate: '2026-09-01',
  devtools: { enabled: false },
  css: ['~/assets/css/main.css'],
  vite: { plugins: [tailwindcss()] },

  app: {
    head: {
      htmlAttrs: { lang: 'ar', dir: 'rtl' },
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'format-detection', content: 'telephone=no' },
        { property: 'og:locale', content: 'ar_EG' },
      ],
      link: [
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700;800&display=swap' },
      ],
    },
  },

  runtimeConfig: {
    // Where the store's server reaches the API (NUXT_API_INTERNAL; the api container in production).
    apiInternal: 'http://localhost:8000/api/v1',
    public: {
      // This site's own address, for canonical links, sitemaps and shares (NUXT_PUBLIC_STORE_URL).
      storeUrl: 'https://store.muhasebi.com',
      // The platform's website («اتعمل بمحاسبي»).
      siteUrl: 'https://muhasebi.com',
    },
  },

  routeRules: {
    // Pages are re-rendered at most every 60 s (served stale while refreshing).
    '/**': { swr: 60 },
    '/*/cart': { ssr: false },
    // Data and media handlers cache themselves (or not at all: proxied images).
    '/api/**': { cache: false },
    '/*/sitemap.xml': { cache: false },
    '/robots.txt': { cache: false },
  },

  nitro: {
    compressPublicAssets: true,
  },

  typescript: { strict: true },
})
