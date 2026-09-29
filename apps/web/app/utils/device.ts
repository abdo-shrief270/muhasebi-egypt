/** "Chrome · Windows" from a user-agent string, for naming and listing signed-in devices. */
export function describeUserAgent(ua: string | null | undefined): string {
  if (!ua) {
    return ''
  }

  const browser = /Edg\//.test(ua)
    ? 'Edge'
    : /OPR\/|Opera/.test(ua)
      ? 'Opera'
      : /SamsungBrowser/.test(ua)
        ? 'Samsung Internet'
        : /Firefox\//.test(ua)
          ? 'Firefox'
          : /Chrome\/|CriOS/.test(ua)
            ? 'Chrome'
            : /Safari\//.test(ua)
              ? 'Safari'
              : ''

  const os = /Windows/.test(ua)
    ? 'Windows'
    : /iPhone/.test(ua)
      ? 'iPhone'
      : /iPad/.test(ua)
        ? 'iPad'
        : /Android/.test(ua)
          ? 'Android'
          : /Mac OS X|Macintosh/.test(ua)
            ? 'Mac'
            : /Linux/.test(ua)
              ? 'Linux'
              : ''

  return [browser, os].filter(Boolean).join(' · ')
}

/** The name this browser signs in as (shown in «الأجهزة اللي داخلة على حسابك»). */
export function currentDeviceName(): string {
  return (import.meta.client ? describeUserAgent(navigator.userAgent) : '') || 'متصفح'
}

/** An icon for a signed-in device. */
export function deviceIcon(ua: string | null | undefined): string {
  if (ua && /iPhone|Android.*Mobile|Mobile Safari/.test(ua)) {
    return 'i-lucide-smartphone'
  }
  if (ua && /iPad|Android|Tablet/.test(ua)) {
    return 'i-lucide-tablet'
  }
  return 'i-lucide-monitor'
}
