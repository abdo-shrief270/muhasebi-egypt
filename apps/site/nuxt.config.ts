// The public website (muhasebi.com): landing page, pricing and the feature guide.
// Prerendered to static HTML (`nuxi generate`) and served by Caddy on the root domain.
export default defineNuxtConfig({
  compatibilityDate: '2026-09-01',
  devtools: { enabled: false },
  modules: ['@nuxt/ui'],
  css: ['~/assets/css/main.css'],

  app: {
    head: {
      htmlAttrs: { lang: 'ar', dir: 'rtl' },
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'theme-color', content: '#0D9488' },
        { property: 'og:site_name', content: 'محاسبي' },
        { property: 'og:locale', content: 'ar_EG' },
        { name: 'twitter:card', content: 'summary_large_image' },
      ],
      link: [
        { rel: 'icon', type: 'image/svg+xml', href: '/favicon.svg' },
        { rel: 'icon', type: 'image/png', sizes: '192x192', href: '/icon-192.png' },
        { rel: 'apple-touch-icon', href: '/apple-touch-icon.png' },
        { rel: 'manifest', href: '/site.webmanifest' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        { rel: 'stylesheet', href: 'https://fonts.googleapis.com/css2?family=Cairo:wght@400;500;600;700;800&display=swap' },
      ],
    },
  },

  ui: { fonts: false },
  colorMode: { preference: 'light', fallback: 'light', storageKey: 'muhasebi-site-color-mode' },

  icon: {
    clientBundle: {
      scan: true,
      // Icons named in data files (features, docs), which the scan doesn't see.
      icons: [
        'lucide:store', 'lucide:bell-ring', 'lucide:ship', 'lucide:shopping-cart', 'lucide:wifi-off', 'lucide:wrench', 'lucide:boxes', 'lucide:scan-line', 'lucide:truck',
        'lucide:users', 'lucide:wallet', 'lucide:chart-column', 'lucide:message-circle', 'lucide:handshake', 'lucide:shield-check',
        'lucide:printer', 'lucide:toggle-right', 'lucide:git-branch', 'lucide:rocket', 'lucide:package', 'lucide:receipt-text',
        'lucide:tag', 'lucide:smartphone', 'lucide:credit-card', 'lucide:lock-keyhole', 'lucide:book-open', 'lucide:life-buoy',
        'lucide:percent', 'lucide:store', 'lucide:undo-2', 'lucide:arrow-left-right', 'lucide:user-cog', 'lucide:file-spreadsheet',
        'lucide:bell', 'lucide:qr-code', 'lucide:hand-coins', 'lucide:settings', 'lucide:sparkles', 'lucide:check', 'lucide:clock',
        'lucide:monitor-smartphone', 'lucide:sun', 'lucide:moon', 'lucide:menu', 'lucide:arrow-left', 'lucide:lightbulb', 'lucide:triangle-alert', 'lucide:calendar-clock', 'lucide:gauge', 'lucide:headphones', 'lucide:circle-alert', 'lucide:facebook', 'lucide:instagram', 'lucide:youtube', 'lucide:music-2', 'lucide:newspaper', 'lucide:clock-3',
      ],
    },
  },

  runtimeConfig: {
    public: {
      // Where the app itself lives (sign-up, sign-in). NUXT_PUBLIC_APP_URL at build time.
      appUrl: 'https://app.muhasebi.com',
      // This site's own address (sitemap, robots). NUXT_PUBLIC_SITE_URL at build time.
      siteUrl: 'https://muhasebi.com',
      // Search Console / Bing Webmaster verification codes (NUXT_PUBLIC_GOOGLE_VERIFICATION, …) at build time.
      googleVerification: '',
      bingVerification: '',
      // Measurement (NUXT_PUBLIC_GA_ID = G-…, NUXT_PUBLIC_META_PIXEL_ID); empty = nothing loaded.
      gaId: '',
      metaPixelId: '',
      // Contact and social pages (NUXT_PUBLIC_WHATSAPP = 2010…, NUXT_PUBLIC_FACEBOOK_URL, …); empty = hidden.
      whatsapp: '',
      facebookUrl: '',
      instagramUrl: '',
      tiktokUrl: '',
      youtubeUrl: '',
      // A campaign bar on every page (NUXT_PUBLIC_OFFER_TEXT, _CODE = a coupon from the admin panel,
      // _UNTIL = YYYY-MM-DD, hidden after it); empty text = no bar.
      offerText: '',
      offerCode: '',
      offerUntil: '',
      // Same-origin API on the website's domain (Caddy proxies /api/v1/public/plans).
      apiBase: '/api/v1',
    },
  },

  nitro: {
    prerender: {
      crawlLinks: true,
      routes: ['/', '/pricing', '/docs', '/for', '/blog', '/sitemap.xml', '/robots.txt', '/llms.txt', '/llms-full.txt'],
    },
    // Dev only: the API on its own port.
    devProxy: { '/api': { target: 'http://localhost:8000/api', changeOrigin: true } },
  },
})
