<template>
  <UModal v-model:open="open" :ui="{ content: 'sm:max-w-2xl' }" title="بحث سريع" description="دوّر على صفحة أو صنف">
    <template #content>
      <UCommandPalette
        v-model:search-term="term"
        :groups="groups"
        :loading="loading"
        placeholder="اكتب اسم صفحة أو صنف أو موديل أو باركود…"
        close
        class="h-96"
        @update:open="open = $event"
      >
        <template #empty>
          <p class="py-6 text-center text-sm text-(--ui-text-muted)">
            {{ term.trim().length < 2 ? 'اكتب حرفين على الأقل عشان تدوّر في الأصناف' : 'مفيش نتايج' }}
          </p>
        </template>
      </UCommandPalette>
    </template>
  </UModal>
</template>

<script setup lang="ts">
import type { CommandPaletteGroup, CommandPaletteItem } from '@nuxt/ui'
import type { Paginated, Product } from '~/types/api'

/** Ctrl/⌘+K or "/" from anywhere: jump to a page, or find a product. */
const open = defineModel<boolean>('open', { default: false })

const api = useApi()
const store = useSessionStore()
const { allItems } = useNavigation()
const actions = useQuickActions()

const term = ref('')
const loading = ref(false)
const products = ref<Product[]>([])

const canSeeProducts = computed(() => store.can('products.view'))
const canEditProducts = computed(() => store.can('products.manage'))

let timer: ReturnType<typeof setTimeout> | undefined
let requestId = 0

watch(term, (value) => {
  clearTimeout(timer)
  const q = value.trim()
  if (!canSeeProducts.value || q.length < 2) {
    products.value = []
    loading.value = false
    return
  }
  timer = setTimeout(async () => {
    const id = ++requestId
    loading.value = true
    try {
      const res = await api<Paginated<Product>>('/products', { query: { q } })
      if (id === requestId) {
        products.value = res.data.slice(0, 8)
      }
    }
    catch {
      if (id === requestId) {
        products.value = []
      }
    }
    finally {
      if (id === requestId) {
        loading.value = false
      }
    }
  }, 250)
})

watch(open, (isOpen) => {
  if (!isOpen) {
    term.value = ''
  }
})

onBeforeUnmount(() => clearTimeout(timer))

function go(to: string) {
  open.value = false
  navigateTo(to)
}

const groups = computed<CommandPaletteGroup<CommandPaletteItem>[]>(() => {
  const result: CommandPaletteGroup<CommandPaletteItem>[] = [{
    id: 'actions',
    label: 'إجراءات سريعة',
    items: actions.value.map(a => ({
      label: a.label,
      suffix: a.description,
      icon: a.icon,
      kbds: a.kbd ? [a.kbd] : undefined,
      onSelect: () => {
        if (a.run) {
          open.value = false
          a.run()
        }
        else if (a.to) {
          go(a.to)
        }
      },
    })),
  }, {
    id: 'pages',
    label: 'الصفحات',
    items: allItems.value.map(item => ({ label: item.label, icon: item.icon, onSelect: () => go(item.to) })),
  }]

  if (products.value.length) {
    result.push({
      id: 'products',
      label: 'الأصناف',
      ignoreFilter: true, // already searched by the server (Arabic spelling, models, barcodes)
      items: products.value.map(p => ({
        label: p.name,
        suffix: [p.category.name, retailPriceRange(p.variants)].join(' · '),
        icon: 'i-lucide-package',
        onSelect: () => go(canEditProducts.value ? `/products/${p.id}` : `/products?q=${encodeURIComponent(p.name)}`),
      })),
    })
  }

  return result
})
</script>
