<template>
  <UTooltip v-if="!online || queued" :text="tooltip">
    <UButton
      :color="failed.length ? 'error' : !online ? 'warning' : 'neutral'"
      variant="soft"
      :icon="!online ? 'i-lucide-wifi-off' : failed.length ? 'i-lucide-cloud-alert' : 'i-lucide-cloud-upload'"
      :aria-label="tooltip"
      :loading="online && syncing && !failed.length"
      @click="panelOpen = true"
    >
      <span v-if="!online" class="font-bold">أوفلاين</span>
      <span v-if="queued" class="num font-bold">{{ queued }}</span>
    </UButton>
  </UTooltip>
</template>

<script setup lang="ts">
/** Top bar: «أوفلاين» when the API can't be reached, and the sales still waiting to be sent. */
const { online } = useConnectivity()
const { entries, failed, syncing, panelOpen } = useOutbox()

const queued = computed(() => entries.value.length)
const tooltip = computed(() => {
  if (failed.value.length) {
    return `${failed.value.length} فاتورة السيرفر رفضها — افتح «فواتير مستنية»`
  }
  if (!online.value) {
    return queued.value ? `النت فاصل · ${queued.value} فاتورة هتتسجل أول ما يرجع` : 'النت فاصل — الكاشير شغال أوفلاين'
  }
  return `${queued.value} فاتورة بتتسجل دلوقتي`
})
</script>
