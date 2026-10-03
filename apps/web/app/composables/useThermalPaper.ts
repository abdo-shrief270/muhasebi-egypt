/**
 * The shop's thermal printer paper (/settings/shop): 80 mm (the default) or 58 mm. Every
 * thermal print (receipts, repair intake, shift report, services slip) sizes its page and
 * content from it.
 */
export function useThermalPaper() {
  const store = useSessionStore()
  const narrow = computed(() => store.session?.tenant.receipt?.paper === '58')
  return {
    page: computed(() => (narrow.value ? '58mm auto' : '80mm auto')),
    width: computed(() => (narrow.value ? '48mm' : '72mm')),
  }
}
