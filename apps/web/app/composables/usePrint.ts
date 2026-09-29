/**
 * Mounts a <PrintSheet>, waits for it (and its barcodes / QR codes) to render, prints, unmounts.
 *
 *   const { printing, print } = usePrint()
 *   <PrintSheet v-if="printing">…</PrintSheet>
 *   await print()
 */
export function usePrint() {
  const printing = ref(false)

  async function print() {
    printing.value = true
    await nextTick()
    // QR codes render asynchronously; give them a frame.
    await new Promise(resolve => setTimeout(resolve, 150))
    window.print()
    printing.value = false
  }

  return { printing: readonly(printing), print }
}
