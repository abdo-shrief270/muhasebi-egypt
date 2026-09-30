const PREFIX = 'muhasebi_cache_'

export function readCache<T>(key: string): T | undefined {
  try {
    const raw = localStorage.getItem(PREFIX + key)
    return raw ? JSON.parse(raw) as T : undefined
  }
  catch {
    return undefined
  }
}

export function writeCache(key: string, value: unknown) {
  try {
    localStorage.setItem(PREFIX + key, JSON.stringify(value))
  }
  catch {
    // Full or blocked storage: offline just won't have it.
  }
}

export function forgetCache(prefix = '') {
  try {
    Object.keys(localStorage).filter(k => k.startsWith(PREFIX + prefix)).forEach(k => localStorage.removeItem(k))
  }
  catch {
    // nothing kept
  }
}

/**
 * A GET that the app still needs with no internet (session, POS options, the open shift): the
 * last good answer is kept in localStorage and returned when the server can't be reached.
 */
export async function withOfflineCache<T>(key: string, fetcher: () => Promise<T>): Promise<T> {
  try {
    const value = await fetcher()
    writeCache(key, value)
    return value
  }
  catch (e) {
    const cached = isNetworkError(e) ? readCache<T>(key) : undefined
    if (cached !== undefined) {
      return cached
    }
    throw e
  }
}

/** A short, non-secret fingerprint (FNV-1a) to key cached data by token without storing any of it. */
export function shortHash(text: string): string {
  let hash = 0x811C9DC5
  for (let i = 0; i < text.length; i++) {
    hash ^= text.charCodeAt(i)
    hash = Math.imul(hash, 0x01000193)
  }
  return (hash >>> 0).toString(36)
}
