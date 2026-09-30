/**
 * «ابعت ملاحظة»: one modal for the whole app (mounted in the default layout), opened from the
 * user menu or Ctrl+K. `appVersion()` is also what the error reporter sends.
 */
export function useFeedback() {
  const open = useState('feedback-open', () => false)

  return {
    open,
    show: () => {
      open.value = true
    },
  }
}

/** This build: the release (NUXT_PUBLIC_APP_VERSION) and Nuxt's build id. */
export function appVersion(): string {
  const config = useRuntimeConfig()
  return [config.public.appVersion, config.app.buildId?.slice(0, 8)].filter(Boolean).join('+').slice(0, 60)
}
