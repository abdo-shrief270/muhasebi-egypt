/**
 * Everything a page tells search engines and link previews: title, description, canonical URL,
 * Open Graph / Twitter cards and JSON-LD (schema.org) — all with absolute URLs.
 */
export function usePageSeo(opts: {
  title: string
  description: string
  path: string
  /** schema.org objects for this page (the site-wide Organization / WebSite are added by the layout) */
  jsonLd?: Record<string, unknown>[]
  type?: 'website' | 'article'
  noindex?: boolean
}) {
  const { siteUrl } = useSiteUrls()
  const url = `${siteUrl}${opts.path === '/' ? '/' : opts.path.replace(/\/$/, '')}`
  const image = `${siteUrl}/og.png`

  useSeoMeta({
    title: opts.title,
    description: opts.description,
    ogTitle: opts.title,
    ogDescription: opts.description,
    ogUrl: url,
    ogType: opts.type ?? 'website',
    ogImage: image,
    ogImageWidth: 1200,
    ogImageHeight: 630,
    ogImageAlt: 'محاسبي — برنامج محلات الموبايلات',
    twitterTitle: opts.title,
    twitterDescription: opts.description,
    twitterImage: image,
    robots: opts.noindex ? 'noindex, nofollow' : 'index, follow, max-image-preview:large',
  })
  useHead({
    link: [
      { rel: 'canonical', href: url },
      { rel: 'alternate', hreflang: 'ar-EG', href: url },
      { rel: 'alternate', hreflang: 'x-default', href: url },
    ],
    script: (opts.jsonLd ?? []).map((data, i) => ({
      key: `ld-${i}`,
      type: 'application/ld+json',
      innerHTML: JSON.stringify({ '@context': 'https://schema.org', ...data }),
    })),
  })
  return { url }
}

/** The site's and the app's absolute addresses (from build-time config). */
export function useSiteUrls() {
  const config = useRuntimeConfig().public
  return {
    siteUrl: config.siteUrl.replace(/\/$/, ''),
    appUrl: config.appUrl.replace(/\/$/, ''),
  }
}

/** schema.org BreadcrumbList from the home page down to this one. */
export function breadcrumbs(trail: { name: string, path: string }[]): Record<string, unknown> {
  const { siteUrl } = useSiteUrls()
  return {
    '@type': 'BreadcrumbList',
    'itemListElement': [{ name: 'محاسبي', path: '/' }, ...trail].map((item, i) => ({
      '@type': 'ListItem',
      'position': i + 1,
      'name': item.name,
      'item': `${siteUrl}${item.path === '/' ? '/' : item.path}`,
    })),
  }
}
