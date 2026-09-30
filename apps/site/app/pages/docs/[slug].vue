<template>
  <div class="mx-auto grid max-w-6xl gap-10 px-4 py-12 lg:grid-cols-[240px_1fr]">
    <!-- Side list of pages (collapses above the text on small screens). -->
    <aside class="lg:sticky lg:top-24 lg:self-start">
      <details class="rounded-2xl border border-(--ui-border) p-4 lg:border-0 lg:p-0" :open="wide">
        <summary class="cursor-pointer font-bold lg:hidden">
          صفحات الشرح
        </summary>
        <nav class="mt-3 space-y-5 lg:mt-0" aria-label="صفحات الشرح">
          <div v-for="(title, key) in docGroups" :key="key">
            <p class="mb-1 text-xs font-bold text-(--ui-text-muted)">
              {{ title }}
            </p>
            <NuxtLink
              v-for="d in docs.filter(x => x.group === key)"
              :key="d.slug"
              :to="`/docs/${d.slug}`"
              class="flex items-center gap-2 rounded-lg px-2 py-1.5 text-sm"
              :class="d.slug === page.slug ? 'bg-(--app-primary-soft) font-bold text-primary' : 'hover:bg-(--ui-bg-elevated)'"
            >
              <UIcon :name="d.icon" class="size-4 shrink-0" />{{ d.title }}
            </NuxtLink>
          </div>
        </nav>
      </details>
    </aside>

    <article class="min-w-0">
      <NuxtLink to="/docs" class="text-sm text-(--ui-text-muted) hover:text-primary">
        شرح البرنامج
      </NuxtLink>
      <h1 class="mt-2 flex items-center gap-3 text-3xl font-extrabold">
        <UIcon :name="page.icon" class="size-7 text-primary" />{{ page.title }}
      </h1>
      <p class="mt-2 text-lg text-(--ui-text-muted)">
        {{ page.summary }}
      </p>

      <div class="prose-ar mt-6">
        <template v-for="(b, i) in page.blocks" :key="i">
          <h2 v-if="b.t === 'h'" :id="`s${i}`">
            {{ b.text }}
          </h2>
          <!-- eslint-disable vue/no-v-html -- our own static guide text (with <kbd>/<b>) -->
          <p v-else-if="b.t === 'p'" v-html="b.text" />
          <ol v-else-if="b.t === 'steps'">
            <li v-for="(s, j) in b.items" :key="j" v-html="s" />
          </ol>
          <ul v-else-if="b.t === 'list'">
            <li v-for="(s, j) in b.items" :key="j" v-html="s" />
          </ul>
          <!-- eslint-enable vue/no-v-html -->
          <UAlert v-else-if="b.t === 'tip'" class="my-5" color="primary" variant="subtle" icon="i-lucide-lightbulb" :description="b.text" />
          <UAlert v-else-if="b.t === 'warn'" class="my-5" color="warning" variant="subtle" icon="i-lucide-triangle-alert" :description="b.text" />
          <figure v-else-if="b.t === 'img'" class="my-8">
            <div :class="b.phone ? 'phone mx-auto w-64' : 'shot'">
              <img :src="b.src" :alt="b.alt" loading="lazy">
            </div>
            <figcaption class="mt-2 text-center text-xs text-(--ui-text-muted)">
              {{ b.alt }}
            </figcaption>
          </figure>
        </template>
      </div>

      <nav class="mt-12 grid gap-3 border-t border-(--ui-border) pt-6 sm:grid-cols-2" aria-label="الصفحة اللي قبل واللي بعد">
        <NuxtLink v-if="prev" :to="`/docs/${prev.slug}`" class="rounded-xl border border-(--ui-border) p-4 hover:border-primary">
          <span class="block text-xs text-(--ui-text-muted)">اللي قبلها</span>
          <span class="font-bold">{{ prev.title }}</span>
        </NuxtLink>
        <span v-else />
        <NuxtLink v-if="next" :to="`/docs/${next.slug}`" class="rounded-xl border border-(--ui-border) p-4 text-end hover:border-primary">
          <span class="block text-xs text-(--ui-text-muted)">اللي بعدها</span>
          <span class="font-bold">{{ next.title }}</span>
        </NuxtLink>
      </nav>

      <div class="mt-10 rounded-2xl bg-(--ui-bg-muted) p-6 text-center">
        <p class="font-extrabold">
          جاهز تجرب؟
        </p>
        <p class="mt-1 text-sm text-(--ui-text-muted)">
          14 يوم ببلاش ومن غير كارت.
        </p>
        <UButton :to="links.register" class="mt-4" label="سجّل محلك" />
      </div>
    </article>
  </div>
</template>

<script setup lang="ts">
import { docBySlug, docGroups, docs } from '~/data/docs'

const route = useRoute()
const links = useAppLinks()
const page = computed(() => {
  const p = docBySlug(String(route.params.slug))
  if (!p) {
    throw createError({ statusCode: 404, statusMessage: 'الصفحة دي مش موجودة', fatal: true })
  }
  return p
})
const index = computed(() => docs.indexOf(page.value))
const prev = computed(() => docs[index.value - 1])
const next = computed(() => docs[index.value + 1])

// The page list is open on wide screens, folded on phones.
const wide = ref(true)
onMounted(() => {
  wide.value = window.matchMedia('(min-width: 1024px)').matches
})

useSeoMeta({
  title: () => page.value.title,
  description: () => page.value.summary,
  ogImage: '/og.png',
})
</script>
