/**
 * The browser twin of the API's Support\Text\SearchText: spelling variants match
 * (أ/إ/آ → ا, ة → ه, ى → ي), no tashkeel or tatweel, lower case, single spaces.
 */
export function normalizeSearch(text: string): string {
  return text
    .replace(/[ً-ْـ]/g, '')
    .replace(/[أإآٱ]/g, 'ا')
    .replace(/ة/g, 'ه')
    .replace(/ى/g, 'ي')
    .replace(/ؤ/g, 'و')
    .replace(/ئ/g, 'ي')
    .toLowerCase()
    .replace(/\s+/g, ' ')
    .trim()
}

export function searchTokens(query: string): string[] {
  const normalized = normalizeSearch(query)
  return normalized === '' ? [] : [...new Set(normalized.split(' '))]
}

/** IMEI / serial the way the API stores it: no spaces, dashes or slashes, upper case. */
export function normalizeSerial(serial: string): string {
  return serial.replace(/[\s\-/]+/g, '').toUpperCase()
}
