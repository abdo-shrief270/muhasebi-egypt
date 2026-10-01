/** The quick price check (استعلام سعر): one window for the whole app, opened from anywhere. */
export function usePriceCheck() {
  const store = useSessionStore()
  const open = useState('price-check-open', () => false)
  const term = useState('price-check-term', () => '')
  /** products.view and the owner's «استعلام السعر السريع» switch: every entry point (top bar, F8, Ctrl+K, home tile) asks this. */
  const available = computed(() => store.can('products.view') && store.hasFeature('inventory.price_check'))

  function show(q = '') {
    if (!available.value) {
      return
    }
    term.value = q
    open.value = true
  }

  return { open, term, available, show }
}
