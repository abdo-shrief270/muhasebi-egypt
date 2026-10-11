/** This site's own data handlers (they call the API with the visitor's IP and cache). */
export function marketFetch<T>(path: string, query?: Record<string, unknown>): Promise<T> {
  return $fetch<T>(`/api/market${path}`, { query })
}

export function useOptions() {
  return useAsyncData('options', () => marketFetch<{ data: import('~/types').Options }>('/options').then(r => r.data).catch(() => null))
}
