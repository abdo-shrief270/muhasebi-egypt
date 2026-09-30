/** Links into the app itself (sign-up / sign-in live on the app's domain). */
export function useAppLinks() {
  const base = useRuntimeConfig().public.appUrl.replace(/\/$/, '')
  return {
    register: `${base}/register`,
    login: `${base}/login`,
    privacy: `${base}/privacy`,
  }
}
