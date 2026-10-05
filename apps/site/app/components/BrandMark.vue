<template>
  <svg viewBox="0 0 100 100" role="img" aria-label="محاسبي" class="shrink-0">
    <defs>
      <linearGradient :id="id" x1="0" y1="0" x2="1" y2="1">
        <stop offset="0" stop-color="#14B8A6" />
        <stop offset="1" stop-color="#0B4F58" />
      </linearGradient>
    </defs>
    <rect width="100" height="100" rx="26" :fill="`url(#${id})`" />
    <g transform="translate(0 -3)">
      <rect x="21" y="52" width="10" height="16" rx="5" fill="#fff" fill-opacity=".5" />
      <rect x="36" y="40" width="10" height="28" rx="5" fill="#fff" fill-opacity=".8" />
      <path d="M77 37 V63 Q77 77 63 77 H26" fill="none" stroke="#fff" stroke-width="10" stroke-linecap="round" />
      <circle cx="64.5" cy="37" r="12.5" fill="none" stroke="#fff" stroke-width="10" />
      <circle cx="64.5" cy="37" r="4" fill="#FBBF24" />
    </g>
  </svg>
</template>

<script setup lang="ts">
/** The brand mark (docs/brand/mark.svg): the letter meem rising out of a small bar chart. Its own colours in both themes. */
const id = useId()
</script>
