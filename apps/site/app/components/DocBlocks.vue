<template>
  <div class="prose-ar">
    <template v-for="(b, i) in blocks" :key="i">
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
</template>

<script setup lang="ts">
import type { DocBlock } from '~/data/docs'

/** The guide's and the articles' text blocks: headings, paragraphs, steps, lists, tips, screenshots. */
defineProps<{ blocks: DocBlock[] }>()
</script>
