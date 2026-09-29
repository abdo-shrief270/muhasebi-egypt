/** The quick price check (استعلام سعر): one window for the whole app, opened from anywhere. */
export function usePriceCheck() {
  const open = useState('price-check-open', () => false)
  const term = useState('price-check-term', () => '')

  function show(q = '') {
    term.value = q
    open.value = true
  }

  return { open, term, show }
}
