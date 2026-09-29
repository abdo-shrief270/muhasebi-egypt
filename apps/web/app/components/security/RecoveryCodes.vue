<template>
  <div class="space-y-3">
    <UAlert
      color="warning"
      variant="subtle"
      icon="i-lucide-triangle-alert"
      title="احفظ الأكواد دي في مكان آمن دلوقتي"
      description="مش هتظهر تاني. لو موبايلك ضاع، كل كود منهم بيدخّلك مرة واحدة بدل كود التطبيق."
    />
    <ol class="grid grid-cols-2 gap-2 rounded-(--ui-radius) bg-(--ui-bg-elevated) p-3 font-mono text-sm num" dir="ltr">
      <li v-for="c in codes" :key="c" class="text-center">
        {{ c }}
      </li>
    </ol>
    <div class="flex flex-wrap gap-2">
      <UButton color="neutral" variant="soft" icon="i-lucide-copy" :label="copied ? 'اتنسخت' : 'نسخ'" @click="copy" />
      <UButton color="neutral" variant="soft" icon="i-lucide-download" label="تنزيل ملف" @click="download" />
    </div>
  </div>
</template>

<script setup lang="ts">
const props = defineProps<{ codes: string[] }>()

const copied = ref(false)
const text = computed(() => `محاسبي — أكواد استرجاع التحقق بخطوتين\n(كل كود بيشتغل مرة واحدة)\n\n${props.codes.join('\n')}\n`)

async function copy() {
  await navigator.clipboard?.writeText(text.value)
  copied.value = true
  setTimeout(() => (copied.value = false), 2000)
}

function download() {
  const url = URL.createObjectURL(new Blob([text.value], { type: 'text/plain;charset=utf-8' }))
  const a = document.createElement('a')
  a.href = url
  a.download = 'muhasebi-recovery-codes.txt'
  a.click()
  URL.revokeObjectURL(url)
}
</script>
