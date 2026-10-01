<template>
  <UApp :locale="ar">
    <NuxtLayout>
      <NuxtPage />
    </NuxtLayout>
  </UApp>
</template>

<script setup lang="ts">
import { ar } from '@nuxt/ui/locale'

const { siteUrl, appUrl } = useSiteUrls()
const verification = useRuntimeConfig().public

useHead({
  // A page title that already names the product keeps it; others get « · محاسبي».
  titleTemplate: t => !t ? 'محاسبي' : t.includes('محاسبي') ? t : `${t} · محاسبي`,
  meta: [
    ...(verification.googleVerification ? [{ name: 'google-site-verification', content: verification.googleVerification }] : []),
    ...(verification.bingVerification ? [{ name: 'msvalidate.01', content: verification.bingVerification }] : []),
  ],
  script: [{
    key: 'ld-site',
    type: 'application/ld+json',
    innerHTML: JSON.stringify({
      '@context': 'https://schema.org',
      '@graph': [
        {
          '@type': 'Organization',
          '@id': `${siteUrl}/#org`,
          'name': 'محاسبي',
          'alternateName': 'Muhasebi',
          'url': siteUrl,
          'logo': `${siteUrl}/icon-512.png`,
          'areaServed': { '@type': 'Country', 'name': 'Egypt' },
        },
        {
          '@type': 'WebSite',
          '@id': `${siteUrl}/#website`,
          'url': siteUrl,
          'name': 'محاسبي',
          'inLanguage': 'ar-EG',
          'publisher': { '@id': `${siteUrl}/#org` },
        },
      ],
    }),
  }],
  link: [{ rel: 'preconnect', href: appUrl }],
})
</script>
